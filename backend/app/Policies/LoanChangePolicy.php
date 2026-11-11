<?php

namespace App\Policies;

use App\Models\LoanChange;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Loan schedule changes (skip / reschedule) are decided by whoever the
 * "loan_change" workflow lets act — never on one's own loan.
 */
class LoanChangePolicy
{
    public function decide(User $user, LoanChange $change): bool
    {
        $approval = $change->latestApprovalRequest;
        $employeeId = $change->loan()->withoutGlobalScopes()->value('employee_id');

        return $change->isPending() && $user->employee_id !== $employeeId
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }
}
