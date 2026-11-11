<?php

namespace App\Services\Payroll;

use App\Enums\LoanInstalmentStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanTransactionType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanInstalment;
use App\Models\User;
use App\Services\Payroll\Data\LoanInstalmentInput;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The payroll hook of loans and advances (Phase 7b calls these):
 *
 *     $instalments->dueFor($employee, $periodStart, $periodEnd);   // list<LoanInstalmentInput>
 *     $instalments->markDeducted($instalment, '500.00', $payrollItem, $user);  // on finalisation
 *     $instalments->reverseDeduction($instalment, $payrollItem, $user);        // correction / reversal
 *
 * dueFor() returns the scheduled instalments of active loans due inside the
 * period, plus older ones still scheduled (overdue = true; an earlier run did
 * not recover them). Until a run is finalised nothing changes, so recalculating
 * a draft run is idempotent.
 */
class LoanInstalments
{
    public function __construct(private readonly LoanLedger $ledger) {}

    /**
     * @return list<LoanInstalmentInput>
     */
    public function dueFor(Employee $employee, CarbonInterface|string $periodStart, CarbonInterface|string $periodEnd): array
    {
        $start = CarbonImmutable::parse($periodStart)->toDateString();
        $end = CarbonImmutable::parse($periodEnd)->toDateString();

        return LoanInstalment::query()
            ->where('employee_id', $employee->id)
            ->scheduled()
            ->where('due_date', '<=', $end)
            ->whereHas('loan', fn ($query) => $query->withoutGlobalScopes()->where('status', LoanStatus::Active))
            ->with(['loan' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderBy('due_date')->orderBy('id')
            ->get()
            ->map(fn (LoanInstalment $row): LoanInstalmentInput => new LoanInstalmentInput(
                instalmentId: $row->id,
                loanId: $row->employee_loan_id,
                loanReference: $row->loan->reference,
                loanType: $row->loan->type,
                sequence: $row->sequence,
                dueDate: $row->due_date->toDateString(),
                amount: $row->remaining(),
                overdue: $row->due_date->toDateString() < $start,
                finalSettlement: $row->is_final_settlement,
            ))->all();
    }

    /**
     * Record that payroll recovered an instalment (the full remaining amount, or less when net pay
     * could not cover it — the rest stays scheduled). Writes the ledger; completes the loan at zero.
     *
     * @param  Model|null  $settledBy  the payroll record (Phase 7b) that deducted it
     */
    public function markDeducted(LoanInstalment $instalment, string $amount, ?Model $settledBy = null, ?User $user = null, ?CarbonInterface $on = null): LoanInstalment
    {
        return DB::transaction(function () use ($instalment, $amount, $settledBy, $user, $on): LoanInstalment {
            $instalment = LoanInstalment::query()->lockForUpdate()->findOrFail($instalment->id);

            if (! $instalment->isScheduled()) {
                throw new InvalidArgumentException('The instalment is not scheduled.');
            }

            $amount = bcadd($amount, '0', 2);

            if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $instalment->remaining(), 2) > 0) {
                throw new InvalidArgumentException('The deducted amount must be positive and at most the remaining instalment.');
            }

            $paid = bcadd((string) $instalment->paid_amount, $amount, 2);
            $full = bccomp($paid, (string) $instalment->amount, 2) === 0;

            $instalment->forceFill([
                'paid_amount' => $paid,
                'status' => $full ? LoanInstalmentStatus::Deducted : LoanInstalmentStatus::Scheduled,
                'settled_by_type' => $settledBy?->getMorphClass(),
                'settled_by_id' => $settledBy?->getKey(),
                'settled_at' => $full ? now() : null,
            ])->save();

            $loan = EmployeeLoan::query()->withoutGlobalScopes()->findOrFail($instalment->employee_loan_id);
            $this->ledger->record($loan, LoanTransactionType::PayrollDeduction, bcmul($amount, '-1', 2), ($on ?? now())->toDateString(), [
                'loan_instalment_id' => $instalment->id,
                'reference' => 'payroll:'.$instalment->id.':'.($settledBy?->getMorphClass() ?? 'manual').':'.($settledBy?->getKey() ?? uniqid()),
            ], $user);

            return $instalment;
        });
    }

    /**
     * Undo a payroll deduction (correction or reversal of a finalised run): the instalment is
     * scheduled again and the ledger gets the amount back. Reopens a completed loan.
     */
    public function reverseDeduction(LoanInstalment $instalment, ?Model $reversedBy = null, ?User $user = null): LoanInstalment
    {
        return DB::transaction(function () use ($instalment, $reversedBy, $user): LoanInstalment {
            $instalment = LoanInstalment::query()->lockForUpdate()->findOrFail($instalment->id);
            $amount = (string) $instalment->paid_amount;

            if (bccomp($amount, '0', 2) <= 0) {
                throw new InvalidArgumentException('Nothing was deducted for this instalment.');
            }

            $instalment->forceFill([
                'paid_amount' => '0.00', 'status' => LoanInstalmentStatus::Scheduled, 'settled_by_type' => null, 'settled_by_id' => null, 'settled_at' => null,
            ])->save();

            $loan = EmployeeLoan::query()->withoutGlobalScopes()->findOrFail($instalment->employee_loan_id);
            $this->ledger->record($loan, LoanTransactionType::DeductionReversal, $amount, now()->toDateString(), [
                'loan_instalment_id' => $instalment->id,
                'reference' => 'reversal:'.$instalment->id.':'.($reversedBy?->getMorphClass() ?? 'manual').':'.($reversedBy?->getKey() ?? uniqid()),
            ], $user);

            return $instalment;
        });
    }
}
