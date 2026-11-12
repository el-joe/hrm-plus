<?php

namespace App\Models;

use App\Enums\LoanSource;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\EmployeeLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee loan or salary advance. Requested in the portal, the mobile
 * API or by an admin on the employee's behalf; approved through the
 * "loan_request" workflow; once approved it has an instalment schedule
 * (loan_instalments) recovered by payroll and a ledger (loan_transactions)
 * whose total is the outstanding balance. See App\Actions\Payroll\ManageLoan.
 */
#[Fillable([
    'reference', 'employee_id', 'type', 'source', 'requested_amount', 'requested_instalments', 'requested_start', 'reason', 'requested_by',
])]
class EmployeeLoan extends Model
{
    /** @use HasFactory<EmployeeLoanFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    public const WORKFLOW = 'loan_request';

    public const CHANGE_WORKFLOW = 'loan_change';

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['requested_amount', 'approved_amount', 'outstanding_amount'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending', 'outstanding_amount' => '0.00'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LoanType::class,
            'status' => LoanStatus::class,
            'source' => LoanSource::class,
            'requested_amount' => 'decimal:2',
            'requested_instalments' => 'integer',
            'requested_start' => 'date',
            'approved_amount' => 'decimal:2',
            'instalment_count' => 'integer',
            'start_date' => 'date',
            'outstanding_amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<LoanInstalment, $this>
     */
    public function instalments(): HasMany
    {
        return $this->hasMany(LoanInstalment::class)->orderBy('due_date')->orderBy('sequence');
    }

    /**
     * @return HasMany<LoanTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class)->orderBy('id');
    }

    /**
     * @return HasMany<LoanChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(LoanChange::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === LoanStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === LoanStatus::Active;
    }

    /**
     * Amount already recovered (approved − outstanding).
     */
    public function repaidAmount(): string
    {
        return $this->approved_amount === null ? '0.00' : bcsub((string) $this->approved_amount, (string) $this->outstanding_amount, 2);
    }
}
