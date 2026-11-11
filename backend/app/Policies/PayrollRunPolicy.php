<?php

namespace App\Policies;

use App\Enums\ApprovalActionType;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Payroll runs. Preparation (payroll.create / update / delete) is separated
 * from approval (payroll.approve / reject through the "payroll_run" workflow)
 * and from finalisation / reversal (payroll_finalisation.manage):
 *
 * - nobody approves a run they submitted (approval engine), and the same person
 *   cannot approve two levels of the same approval request;
 * - nobody approves or finalises an off-cycle run (correction, final settlement)
 *   that pays themselves;
 * - a data-scoped admin sees a run only when it contains employees of their
 *   scope, and only those employees inside it.
 */
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.view') && $run->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.create');
    }

    public function update(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.update') && $run->status->isEditable() && $run->isVisibleTo($user);
    }

    public function submit(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.update') && $run->status === PayrollRunStatus::Calculated && $run->isVisibleTo($user);
    }

    public function cancel(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.delete') && $run->status->isEditable() && $run->isVisibleTo($user);
    }

    public function decide(User $user, PayrollRun $run): bool
    {
        $approval = $run->latestApprovalRequest;

        if ($run->status !== PayrollRunStatus::PendingApproval || $approval === null || ! $approval->isPending()) {
            return false;
        }

        if (! $user->can('payroll.approve') && ! $user->can('payroll.reject')) {
            return false;
        }

        $alreadyApproved = $approval->actions()->where('action', ApprovalActionType::Approved)->where('acted_by', $user->id)->exists();

        return ! $alreadyApproved && ! $this->paysThemselves($user, $run) && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function finalise(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll_finalisation.manage') && $run->status === PayrollRunStatus::Approved
            && $run->isVisibleTo($user) && ! $this->paysThemselves($user, $run);
    }

    public function reverse(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll_finalisation.manage') && $run->status === PayrollRunStatus::Finalised
            && $run->type !== PayrollRunType::Reversal && $run->isVisibleTo($user);
    }

    public function export(User $user, PayrollRun $run): bool
    {
        return $user->can('payroll.export') && $run->isVisibleTo($user) && $run->status !== PayrollRunStatus::Draft;
    }

    public function viewItem(User $user, PayrollRun $run, PayrollItem $item): bool
    {
        return $this->view($user, $run) && $item->payroll_run_id === $run->id && $item->isVisibleTo($user);
    }

    private function paysThemselves(User $user, PayrollRun $run): bool
    {
        return $run->type !== PayrollRunType::Regular && $user->employee_id !== null
            && $run->items()->withoutGlobalScopes()->where('employee_id', $user->employee_id)->exists();
    }
}
