<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Employees\Contracts\ProfileTab;

/**
 * "Performance" tab of the admin employee profile: reviews (with scores and the
 * final rating) and goals. Shown to holders of performance.view who may see this
 * employee's performance (HR with performance.manage, or the employee's managers).
 */
class EmployeePerformanceTab implements ProfileTab
{
    public function __construct(private readonly PerformanceVisibility $visibility) {}

    public function visibleTo(User $user, Employee $employee): bool
    {
        return $user->can('performance.view') && $this->visibility->canSeeEmployee($user, $employee);
    }

    public function data(User $user, Employee $employee): array
    {
        return [
            'performanceReviews' => PerformanceReview::query()->where('employee_id', $employee->id)
                ->with(['cycle', 'finalRating', 'reviewer'])->latest('id')->get(),
            'performanceGoals' => PerformanceGoal::query()->where('employee_id', $employee->id)
                ->with('cycle')->latest('id')->limit(50)->get(),
        ];
    }
}
