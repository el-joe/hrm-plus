<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Corrections: listed with attendance.view inside the data scope; decided
 * by whoever the approval engine lets act on the current step.
 */
class AttendanceCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceCorrection $correction): bool
    {
        return $user->can('attendance.view') && $correction->isVisibleTo($user);
    }

    public function decide(User $user, AttendanceCorrection $correction): bool
    {
        $request = $correction->latestApprovalRequest;

        return $correction->isPending() && $request !== null && app(ApprovalService::class)->canAct($request, $user);
    }
}
