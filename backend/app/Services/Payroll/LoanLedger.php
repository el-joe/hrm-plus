<?php

namespace App\Services\Payroll;

use App\Enums\LoanInstalmentStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanTransactionType;
use App\Models\EmployeeLoan;
use App\Models\LoanTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of loan_transactions. Each row carries the balance after
 * it; employee_loans.outstanding_amount is kept equal to the ledger total in
 * the same transaction. A loan whose balance reaches zero is completed;
 * a reversal that raises a completed loan's balance reopens it.
 */
class LoanLedger
{
    /**
     * @param  array{loan_instalment_id?: int|null, reference?: string|null, note?: string|null}  $attributes
     */
    public function record(EmployeeLoan $loan, LoanTransactionType $type, string $amount, string $date, array $attributes = [], ?User $user = null): LoanTransaction
    {
        return DB::transaction(function () use ($loan, $type, $amount, $date, $attributes, $user): LoanTransaction {
            $loan = EmployeeLoan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($loan->id);
            $balance = bcadd((string) $loan->outstanding_amount, $amount, 2);

            $row = $loan->transactions()->create([
                ...$attributes,
                'type' => $type,
                'amount' => bcadd($amount, '0', 2),
                'balance_after' => $balance,
                'occurred_on' => $date,
                'created_by' => $user?->id,
            ]);

            $updates = ['outstanding_amount' => $balance];

            if (bccomp($balance, '0', 2) <= 0 && $loan->status === LoanStatus::Active) {
                $updates += ['status' => LoanStatus::Completed, 'completed_at' => now()];
            } elseif (bccomp($balance, '0', 2) > 0 && $loan->status === LoanStatus::Completed) {
                $updates += ['status' => LoanStatus::Active, 'completed_at' => null];
            }

            $loan->forceFill($updates)->save();

            if (($updates['status'] ?? null) === LoanStatus::Completed) {
                $loan->instalments()->where('status', LoanInstalmentStatus::Scheduled)->update(['status' => LoanInstalmentStatus::Cancelled]);
            }

            return $row;
        });
    }

    /**
     * The ledger total (should always equal outstanding_amount).
     */
    public function balance(EmployeeLoan $loan): string
    {
        return bcadd((string) LoanTransaction::query()->where('employee_loan_id', $loan->id)->sum('amount'), '0', 2);
    }
}
