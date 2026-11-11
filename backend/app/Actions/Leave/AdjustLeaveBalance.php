<?php

namespace App\Actions\Leave;

use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePolicyResolver;
use App\Services\Leave\LeaveYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual balance entries (admin, leave_balances.update), always with a
 * reason and an audit record:
 *
 * - adjustment: a signed correction (+ credit, - debit);
 * - encashment: unused days paid out — only when the policy is encashable,
 *   within its maximum per year and the available balance. Payroll reads
 *   these rows through LeavePayrollFeed::encashments().
 */
class AdjustLeaveBalance
{
    public function __construct(
        private readonly LeaveLedger $ledger,
        private readonly LeaveBalanceCalculator $balances,
        private readonly LeavePolicyResolver $policies,
        private readonly LeaveYear $years,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{leave_type_id: int|string, leave_year: int|string, type: string, amount: string, effective_date: string, reason: string}  $data
     */
    public function handle(Employee $employee, array $data, User $user): LeaveBalanceTransaction
    {
        $type = LeaveType::query()->findOrFail($data['leave_type_id']);
        $kind = LeaveTransactionType::from($data['type']);
        $year = (int) $data['leave_year'];
        $amount = bcadd((string) $data['amount'], '0', 2);

        if (! $type->tracks_balance) {
            throw ValidationException::withMessages(['leave_type_id' => __('leave.validation.no_balance_type')]);
        }

        if (! in_array($kind, LeaveTransactionType::manual(), true) || bccomp($amount, '0', 2) === 0) {
            throw ValidationException::withMessages(['amount' => __('leave.validation.amount_required')]);
        }

        return DB::transaction(function () use ($employee, $type, $kind, $year, $amount, $data, $user): LeaveBalanceTransaction {
            Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();
            $policy = $this->policies->for($employee, $type, $this->years->end($year)->min(now()->startOfDay()->toImmutable()));

            if ($kind === LeaveTransactionType::Encashment) {
                $amount = bcmul(ltrim($amount, '-'), '-1', 2);
                $days = bcmul($amount, '-1', 2);

                if ($policy === null || ! $policy->encashable) {
                    throw ValidationException::withMessages(['type' => __('leave.validation.not_encashable')]);
                }

                $already = bcmul($this->ledger->sum($employee->id, $type->id, $year, [LeaveTransactionType::Encashment]), '-1', 2);

                if ($policy->encashment_max_days !== null && bccomp(bcadd($already, $days, 2), (string) $policy->encashment_max_days, 2) > 0) {
                    throw ValidationException::withMessages(['amount' => __('leave.validation.encashment_max', ['days' => $policy->encashment_max_days])]);
                }

                if (bccomp($days, $this->balances->for($employee, $type, $year)->available, 2) > 0) {
                    throw ValidationException::withMessages(['amount' => __('leave.validation.encashment_balance')]);
                }
            }

            $row = $this->ledger->record($employee, $type, $year, $kind, $amount, $data['effective_date'], [
                'leave_policy_id' => $policy?->id, 'reason' => $data['reason'],
            ], $user);

            $this->audit->log('leave.balance_'.$kind->value, $row, meta: [
                'employee_id' => $employee->id, 'leave_type' => $type->code, 'leave_year' => $year, 'amount' => $row->amount, 'reason' => $data['reason'],
            ], actor: $user);

            return $row;
        });
    }
}
