<?php

namespace App\Policies;

use App\Models\ScheduleChangeRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Shift changes and swaps: raised with schedules.create for employees in
 * scope, decided by whoever the approval engine lets act.
 */
class ScheduleChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('schedules.create');
    }

    public function decide(User $user, ScheduleChangeRequest $request): bool
    {
        $approval = $request->latestApprovalRequest;

        return $request->isPending() && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }
}
