<?php

namespace App\Models;

use App\Enums\LoanInstalmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One instalment of a loan schedule. Payroll reads the scheduled ones
 * through App\Services\Payroll\LoanInstalments::dueFor() and marks them
 * deducted when a run is finalised (markDeducted), which writes the ledger.
 */
#[Fillable(['employee_loan_id', 'employee_id', 'sequence', 'due_date', 'amount', 'paid_amount', 'status', 'is_final_settlement'])]
class LoanInstalment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => LoanInstalmentStatus::class,
            'is_final_settlement' => 'boolean',
            'settled_at' => 'datetime',
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
     * The payroll record that recovered it (set by Phase 7b).
     *
     * @return MorphTo<Model, $this>
     */
    public function settledBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), LoanInstalmentStatus::Scheduled);
    }

    public function isScheduled(): bool
    {
        return $this->status === LoanInstalmentStatus::Scheduled;
    }

    public function remaining(): string
    {
        return bcsub((string) $this->amount, (string) $this->paid_amount, 2);
    }
}
