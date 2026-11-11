<?php

namespace App\Policies;

use App\Models\AttendanceManualBatch;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Bulk manual attendance and imports: created with attendance.create; a
 * draft is confirmed / discarded only by its author; a pending batch is
 * decided by whoever the approval engine lets act.
 */
class AttendanceManualBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceManualBatch $batch): bool
    {
        return $user->can('attendance.view') && ($batch->created_by === $user->id || $batch->isVisibleTo($user));
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.create');
    }

    public function confirm(User $user, AttendanceManualBatch $batch): bool
    {
        return $user->can('attendance.create') && $batch->isDraft() && $batch->created_by === $user->id;
    }

    public function decide(User $user, AttendanceManualBatch $batch): bool
    {
        $request = $batch->latestApprovalRequest;

        return $request !== null && $request->isPending() && app(ApprovalService::class)->canAct($request, $user);
    }
}
