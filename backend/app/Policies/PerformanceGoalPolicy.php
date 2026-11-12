<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Models\User;
use App\Services\Performance\PerformanceVisibility;

/**
 * Goals and KPIs in the admin area: performance.view to list (reporting line
 * without performance.manage), performance.create to set goals for a visible
 * employee, performance.update to edit them and record progress. Never one's own
 * goals (the portal handles those).
 */
class PerformanceGoalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('performance.view');
    }

    public function view(User $user, PerformanceGoal $goal): bool
    {
        return $user->can('performance.view') && $this->employeeVisible($user, $goal->employee);
    }

    public function create(User $user): bool
    {
        return $user->can('performance.create');
    }

    public function createFor(User $user, Employee $employee): bool
    {
        return $user->can('performance.create') && app(PerformanceVisibility::class)->canSeeEmployee($user, $employee);
    }

    public function update(User $user, PerformanceGoal $goal): bool
    {
        return $user->can('performance.update') && $this->employeeVisible($user, $goal->employee);
    }

    private function employeeVisible(User $user, ?Employee $employee): bool
    {
        return $employee !== null && app(PerformanceVisibility::class)->canSeeEmployee($user, $employee);
    }
}
