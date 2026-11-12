<?php

namespace App\Listeners;

use App\Actions\HrRequests\ManageHrRequest;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\HrRequest;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * The "hr_request" approval workflow finished: an approved request reaches HR (opened, SLA starts), a
 * rejected one is closed and the employee told.
 */
class SyncHrRequestApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $approval = $event->approvalRequest;

        if ($approval->approvable_type !== (new HrRequest)->getMorphClass()) {
            return;
        }

        $this->scopes->withoutScoping(function () use ($approval, $event): void {
            $request = HrRequest::query()->withoutGlobalScopes()->find($approval->approvable_id);
            $last = $approval->actions()->reorder()->latest('id')->first();

            if ($request === null) {
                return;
            }

            app(ManageHrRequest::class)->applyApprovalOutcome(
                $request,
                $event instanceof ApprovalRequestApproved,
                User::query()->find($last?->acted_by),
                $event instanceof ApprovalRequestRejected ? $event->reason : null,
            );
        });
    }
}
