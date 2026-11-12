<?php

namespace App\Models;

use App\Enums\LoanTransactionType;
use Database\Factories\LoanTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of a loan's append-only ledger. amount > 0 raises the outstanding
 * balance (disbursement, reversal of a deduction), amount < 0 lowers it
 * (payroll deduction, early repayment). Never updated or deleted.
 */
#[Fillable(['employee_loan_id', 'loan_instalment_id', 'type', 'amount', 'balance_after', 'occurred_on', 'reference', 'note', 'created_by'])]
class LoanTransaction extends Model
{
    /** @use HasFactory<LoanTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LoanTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<EmployeeLoan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    /**
     * @return BelongsTo<LoanInstalment, $this>
     */
    public function instalment(): BelongsTo
    {
        return $this->belongsTo(LoanInstalment::class, 'loan_instalment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
