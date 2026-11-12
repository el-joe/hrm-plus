<?php

namespace App\Policies;

use App\Models\DisciplinaryAction;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Decisions on proposed disciplinary actions: whoever the approval engine lets
 * act on the current step (disciplinary.approve by default, data scope, never the
 * proposer), never on an action about yourself, and only with access to the case file.
 * Withdrawing a pending proposal: its proposer or another disciplinary.update holder.
 */
class DisciplinaryActionPolicy
{
    public function decide(User $user, DisciplinaryAction $action): bool
    {
        $approval = $action->latestApprovalRequest;

        return $action->isPending()
            && $user->can('disciplinary.view')
            && $user->employee_id !== $action->employee_id
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function cancel(User $user, DisciplinaryAction $action): bool
    {
        return $action->isPending() && $action->isVisibleTo($user)
            && ($action->proposed_by === $user->id || $user->can('disciplinary.update'))
            && $user->can('disciplinary.view');
    }
}
