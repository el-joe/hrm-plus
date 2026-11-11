<?php

namespace App\Listeners;

use App\Actions\Leave\ManageLeaveRequest;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies the final outcome of leave approval runs: the request workflow of
 * the leave type and the "leave_cancellation" workflow.
 */
class SyncLeaveApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $approval = $event->approvalRequest;

        if ($approval->approvable_type !== (new LeaveRequest)->getMorphClass()) {
            return;
        }

        $this->scopes->withoutScoping(function () use ($approval, $event): void {
            $request = LeaveRequest::query()->withoutGlobalScopes()->find($approval->approvable_id);
            $last = $approval->actions()->reorder()->latest('id')->first();

            if ($request === null) {
                return;
            }

            app(ManageLeaveRequest::class)->applyApprovalOutcome(
                $request,
                $approval,
                $event instanceof ApprovalRequestApproved,
                User::query()->find($last?->acted_by),
                $event instanceof ApprovalRequestRejected ? $event->reason : null,
            );
        });
    }
}
