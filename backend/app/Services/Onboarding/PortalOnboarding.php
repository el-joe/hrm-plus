<?php

namespace App\Services\Onboarding;

use App\Enums\OnboardingStatus;
use App\Enums\TaskAssignee;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\OnboardingTask;

/**
 * The employee's own onboarding (portal and mobile API): the latest
 * onboarding that is running (or else the latest completed one). Employees
 * see every task for transparency but can only complete their own
 * ("employee" assignee).
 */
class PortalOnboarding
{
    public function current(Employee $employee): ?Onboarding
    {
        return Onboarding::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->getKey())
            ->whereIn('status', [OnboardingStatus::InProgress, OnboardingStatus::Completed])
            ->orderByRaw('status = ? desc', [OnboardingStatus::InProgress->value])
            ->latest('id')
            ->with('tasks')
            ->first();
    }

    /**
     * A task of this employee that the employee may complete, or null.
     */
    public function ownTask(Employee $employee, int $taskId): ?OnboardingTask
    {
        return OnboardingTask::query()
            ->whereKey($taskId)
            ->where('assignee', TaskAssignee::Employee)
            ->whereHas('onboarding', fn ($query) => $query->withoutGlobalScopes()
                ->where('employee_id', $employee->getKey())
                ->where('status', '!=', OnboardingStatus::Cancelled))
            ->first();
    }
}
