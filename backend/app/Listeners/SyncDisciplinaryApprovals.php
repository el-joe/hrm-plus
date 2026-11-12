<?php

namespace App\Listeners;

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\DisciplinaryAction;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies the final outcome of the "disciplinary_action" workflow: issue the
 * approved action (and file its deduction), or send the case back to investigation.
 */
class SyncDisciplinaryApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $approval = $event->approvalRequest;

        if ($approval->approvable_type !== (new DisciplinaryAction)->getMorphClass()) {
            return;
        }

        $this->scopes->withoutScoping(function () use ($approval, $event): void {
            $action = DisciplinaryAction::query()->withoutGlobalScopes()->find($approval->approvable_id);
            $last = $approval->actions()->reorder()->latest('id')->first();

            if ($action === null) {
                return;
            }

            app(ManageDisciplinaryAction::class)->applyApprovalOutcome(
                $action,
                $event instanceof ApprovalRequestApproved,
                User::query()->find($last?->acted_by),
                $event instanceof ApprovalRequestRejected ? $event->reason : null,
            );
        });
    }
}
