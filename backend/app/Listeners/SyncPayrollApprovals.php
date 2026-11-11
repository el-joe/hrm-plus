<?php

namespace App\Listeners;

use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\ManagePayEntry;
use App\Actions\Payroll\ManagePayrollRun;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\EmployeeLoan;
use App\Models\LoanChange;
use App\Models\PayEntry;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies the final outcome of the payroll approval workflows: allowance /
 * deduction entries, loan requests, loan schedule changes and payroll runs (Phase 7b).
 */
class SyncPayrollApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $approval = $event->approvalRequest;
        $types = [(new PayEntry)->getMorphClass(), (new EmployeeLoan)->getMorphClass(), (new LoanChange)->getMorphClass(), (new PayrollRun)->getMorphClass()];

        if (! in_array($approval->approvable_type, $types, true)) {
            return;
        }

        $this->scopes->withoutScoping(function () use ($approval, $event): void {
            $approved = $event instanceof ApprovalRequestApproved;
            $reason = $event instanceof ApprovalRequestRejected ? $event->reason : null;
            $last = $approval->actions()->reorder()->latest('id')->first();
            $decidedBy = User::query()->find($last?->acted_by);

            $id = $approval->approvable_id;

            if ($approval->approvable_type === (new PayEntry)->getMorphClass()) {
                $entry = PayEntry::query()->withoutGlobalScopes()->find($id);
                if ($entry !== null) {
                    app(ManagePayEntry::class)->applyApprovalOutcome($entry, $approved, $decidedBy, $reason);
                }
            } elseif ($approval->approvable_type === (new EmployeeLoan)->getMorphClass()) {
                $loan = EmployeeLoan::query()->withoutGlobalScopes()->find($id);
                if ($loan !== null) {
                    app(ManageLoan::class)->applyApprovalOutcome($loan, $approved, $decidedBy, $reason);
                }
            } elseif ($approval->approvable_type === (new PayrollRun)->getMorphClass()) {
                $run = PayrollRun::query()->withoutGlobalScopes()->find($id);
                if ($run !== null) {
                    app(ManagePayrollRun::class)->applyApprovalOutcome($run, $approved, $decidedBy, $reason);
                }
            } else {
                $change = LoanChange::query()->withoutGlobalScopes()->find($id);
                if ($change !== null) {
                    app(ManageLoan::class)->applyChangeOutcome($change, $approved, $decidedBy, $reason);
                }
            }
        });
    }
}
