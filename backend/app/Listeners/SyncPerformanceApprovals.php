<?php

namespace App\Listeners;

use App\Actions\Performance\ManagePerformanceReview;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies the final outcome of the "performance_review" calibration workflow.
 */
class SyncPerformanceApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $approval = $event->approvalRequest;

        if ($approval->approvable_type !== (new PerformanceReview)->getMorphClass()) {
            return;
        }

        $this->scopes->withoutScoping(function () use ($approval, $event): void {
            $review = PerformanceReview::query()->withoutGlobalScopes()->find($approval->approvable_id);
            $last = $approval->actions()->reorder()->latest('id')->first();

            if ($review === null) {
                return;
            }

            app(ManagePerformanceReview::class)->applyApprovalOutcome(
                $review,
                $event instanceof ApprovalRequestApproved,
                User::query()->find($last?->acted_by),
                $event instanceof ApprovalRequestRejected ? $event->reason : null,
            );
        });
    }
}
