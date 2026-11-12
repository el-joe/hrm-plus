<?php

namespace App\Listeners;

use App\Actions\Training\ManageTrainingEnrolment;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies the final outcome of the "training_enrolment" workflow (manager
 * nominations and employees' own training requests).
 */
class SyncTrainingApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $approval = $event->approvalRequest;

        if ($approval->approvable_type !== (new TrainingEnrolment)->getMorphClass()) {
            return;
        }

        $this->scopes->withoutScoping(function () use ($approval, $event): void {
            $enrolment = TrainingEnrolment::query()->withoutGlobalScopes()->find($approval->approvable_id);
            $last = $approval->actions()->reorder()->latest('id')->first();

            if ($enrolment === null) {
                return;
            }

            app(ManageTrainingEnrolment::class)->applyApprovalOutcome(
                $enrolment,
                $event instanceof ApprovalRequestApproved,
                User::query()->find($last?->acted_by),
                $event instanceof ApprovalRequestRejected ? $event->reason : null,
            );
        });
    }
}
