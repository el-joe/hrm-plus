<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which reviews and goals an admin may see, on top of the data scope (the
 * global HasDataScope filter on reviews / goals through the employee):
 *
 * - performance.manage: everything inside the data scope;
 * - otherwise (managers): employees in their reporting line — direct and
 *   indirect reports through employees.manager_id — plus reviews they are the
 *   assigned reviewer of. An admin not linked to an employee sees none.
 *
 * Their own review / goals are never part of their admin view (they use the portal).
 */
class PerformanceVisibility
{
    /** @var array<int, list<int>> */
    private array $lines = [];

    public function seesAll(User $user): bool
    {
        return $user->can('performance.manage');
    }

    /**
     * Ids of every employee below the user's employee in the reporting line (any depth).
     *
     * @return list<int>
     */
    public function reportingLine(User $user): array
    {
        if ($user->employee_id === null) {
            return [];
        }

        return $this->lines[$user->employee_id] ??= $this->reportsOf((int) $user->employee_id);
    }

    /**
     * @return list<int>
     */
    public function reportsOf(int $employeeId): array
    {
        $found = [];
        $frontier = [$employeeId];

        for ($depth = 0; $frontier !== [] && $depth < 25; $depth++) {
            $frontier = Employee::query()->withoutGlobalScopes()
                ->whereIn('manager_id', $frontier)
                ->whereNotIn('id', [...$found, $employeeId])
                ->pluck('id')->map(fn ($id): int => (int) $id)->all();

            array_push($found, ...$frontier);
        }

        return $found;
    }

    /**
     * @param  Builder<PerformanceReview>  $query
     * @return Builder<PerformanceReview>
     */
    public function reviews(Builder $query, User $user): Builder
    {
        $query->when($user->employee_id !== null, fn (Builder $query) => $query->where($query->qualifyColumn('employee_id'), '!=', $user->employee_id));

        if ($this->seesAll($user)) {
            return $query;
        }

        $line = $this->reportingLine($user);

        return $query->where(function (Builder $query) use ($user, $line): void {
            $query->whereIn($query->qualifyColumn('employee_id'), $line === [] ? [0] : $line);

            if ($user->employee_id !== null) {
                $query->orWhere($query->qualifyColumn('reviewer_id'), $user->employee_id);
            }
        });
    }

    /**
     * @param  Builder<PerformanceGoal>  $query
     * @return Builder<PerformanceGoal>
     */
    public function goals(Builder $query, User $user): Builder
    {
        $query->when($user->employee_id !== null, fn (Builder $query) => $query->where($query->qualifyColumn('employee_id'), '!=', $user->employee_id));

        if ($this->seesAll($user)) {
            return $query;
        }

        $line = $this->reportingLine($user);

        return $query->whereIn($query->qualifyColumn('employee_id'), $line === [] ? [0] : $line);
    }

    /**
     * Employees whose goals / reviews the user may handle (for pickers).
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function employees(Builder $query, User $user): Builder
    {
        $query->when($user->employee_id !== null, fn (Builder $query) => $query->whereKeyNot($user->employee_id));

        if ($this->seesAll($user)) {
            return $query;
        }

        $line = $this->reportingLine($user);

        return $query->whereKey($line === [] ? [0] : $line);
    }

    public function canSeeReview(User $user, PerformanceReview $review): bool
    {
        if ($user->employee_id !== null && $review->employee_id === $user->employee_id) {
            return false;
        }

        if (! $review->isVisibleTo($user)) {
            return false;
        }

        return $this->seesAll($user)
            || ($user->employee_id !== null && $review->reviewer_id === $user->employee_id)
            || in_array($review->employee_id, $this->reportingLine($user), true);
    }

    public function canSeeEmployee(User $user, Employee $employee): bool
    {
        if ($user->employee_id !== null && $employee->id === $user->employee_id) {
            return false;
        }

        return $employee->isVisibleTo($user) && ($this->seesAll($user) || in_array($employee->id, $this->reportingLine($user), true));
    }
}
