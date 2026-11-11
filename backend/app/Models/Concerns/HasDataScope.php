<?php

namespace App\Models\Concerns;

use App\Models\Scopes\DataScope;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Opt a model into admin data scoping (branch / department / employee group):
 *
 *     use HasDataScope;
 *
 * Inside the admin area every query on the model is then limited to the rows
 * the signed-in admin may see, and policies can call `$model->isVisibleTo($user)`.
 *
 * By default the model is expected to have `branch_id` and `department_id`
 * columns. Override dataScopeMap() when it differs. A value is either a column
 * on the model's table or "relation.column" (nested relations allowed) for
 * models that are scoped through a parent, e.g.:
 *
 *     public function dataScopeMap(): array
 *     {
 *         return [
 *             'branch' => 'employee.branch_id',
 *             'department' => 'employee.department_id',
 *             'employee_group' => 'employee.groups.id',
 *         ];
 *     }
 *
 * @mixin Model
 */
trait HasDataScope
{
    public static function bootHasDataScope(): void
    {
        static::addGlobalScope(new DataScope);
    }

    /**
     * Map of App\Enums\DataScopeType value => column or "relation.column".
     *
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'department_id',
        ];
    }

    /**
     * Limit the query to rows the given admin may access. Use this explicitly
     * outside the admin area (jobs, exports, commands).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        app(DataScopeManager::class)->apply($query, $user);
    }

    /**
     * Whether the given admin may access this record. For policies.
     */
    public function isVisibleTo(User $user): bool
    {
        return app(DataScopeManager::class)->allows($user, $this);
    }
}
