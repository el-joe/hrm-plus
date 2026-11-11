<?php

namespace App\Services\Authorization;

use App\Enums\DataScopeType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Restricts what an admin can see to their assigned branches, departments
 * and employee groups (admin_data_scopes).
 *
 * Rules:
 * - Super Admins and admins with no assigned scopes are unrestricted.
 * - A restricted admin sees a row when it matches ANY of their assigned
 *   scopes (branch 1 OR department 5 ...).
 * - If the model maps none of the dimensions the admin is restricted by, the
 *   admin sees nothing (deny by default).
 * - A department restriction includes every sub-department below it (at any
 *   depth), resolved when the scope is applied, so a department added later
 *   under it is covered too.
 *
 * Registered as a singleton. The admin area middleware calls activate() so
 * models using the HasDataScope trait are filtered automatically.
 */
class DataScopeManager
{
    private ?User $activeAdmin = null;

    /**
     * Make the given admin's restrictions apply to all HasDataScope models.
     */
    public function activate(User $admin): void
    {
        $this->activeAdmin = $admin;
    }

    public function deactivate(): void
    {
        $this->activeAdmin = null;
    }

    public function activeAdmin(): ?User
    {
        return $this->activeAdmin;
    }

    /**
     * Run a callback with automatic scoping switched off, e.g. to check
     * uniqueness across the whole company. Use sparingly.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutScoping(callable $callback): mixed
    {
        $admin = $this->activeAdmin;
        $this->activeAdmin = null;

        try {
            return $callback();
        } finally {
            $this->activeAdmin = $admin;
        }
    }

    /**
     * Constrain the query to rows the admin may access.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, User $admin): Builder
    {
        if (! $admin->hasDataRestrictions()) {
            return $query;
        }

        $map = $query->getModel()->dataScopeMap();
        $constraints = array_intersect_key($this->effectiveScopeIds($admin), $map);

        if ($constraints === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($constraints, $map): void {
            foreach ($constraints as $type => $ids) {
                $target = $map[$type];

                if (! str_contains($target, '.')) {
                    $query->orWhereIn($query->qualifyColumn($target), $ids);

                    continue;
                }

                $relation = Str::beforeLast($target, '.');
                $column = Str::afterLast($target, '.');

                $query->orWhereHas($relation, function (Builder $related) use ($column, $ids): void {
                    $related->whereIn($related->qualifyColumn($column), $ids);
                });
            }
        });
    }

    /**
     * The admin's scope ids as enforced: assigned department ids are expanded
     * with all their sub-departments.
     *
     * @return array<string, list<int>>
     */
    public function effectiveScopeIds(User $admin): array
    {
        $scopes = $admin->dataScopeIds();
        $type = DataScopeType::Department->value;

        if (isset($scopes[$type])) {
            $scopes[$type] = $this->withSubDepartments($scopes[$type]);
        }

        return $scopes;
    }

    /**
     * @param  list<int>  $departmentIds
     * @return list<int>
     */
    public function withSubDepartments(array $departmentIds): array
    {
        $all = $departmentIds;
        $frontier = $departmentIds;

        while ($frontier !== []) {
            $frontier = Department::query()->withoutGlobalScopes()
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $all)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            array_push($all, ...$frontier);
        }

        sort($all);

        return array_values(array_unique($all));
    }

    /**
     * Whether the admin may access this single record.
     */
    public function allows(User $admin, Model $model): bool
    {
        if (! $admin->hasDataRestrictions()) {
            return true;
        }

        $query = $model->newQueryWithoutScopes()->whereKey($model->getKey());

        return $this->apply($query, $admin)->exists();
    }

    /**
     * Human-readable scope values: branch, department and employee group ids
     * become "CODE · Name" (ids that no longer exist stay as "#id").
     *
     * @param  array<string, list<int>>  $scopeIds  as returned by User::dataScopeIds()
     * @return array<string, list<string>>
     */
    public function describe(array $scopeIds): array
    {
        $models = [
            DataScopeType::Branch->value => Branch::class,
            DataScopeType::Department->value => Department::class,
            DataScopeType::EmployeeGroup->value => EmployeeGroup::class,
        ];
        $described = [];

        foreach ($scopeIds as $type => $ids) {
            if (! isset($models[$type])) {
                $described[$type] = array_map(fn (int $id): string => '#'.$id, $ids);

                continue;
            }

            $records = $this->withoutScoping(fn () => $models[$type]::query()->whereKey($ids)->get()->keyBy('id'));
            $described[$type] = array_map(
                fn (int $id): string => $records->has($id) ? $records[$id]->code.' · '.$records[$id]->name : '#'.$id,
                $ids,
            );
        }

        return $described;
    }
}
