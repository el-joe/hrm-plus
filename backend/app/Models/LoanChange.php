<?php

namespace App\Models;

use App\Enums\LoanChangeType;
use App\Enums\RequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\LoanChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A requested change to an active loan's schedule — skip one instalment
 * (moved to the end) or reschedule the remaining balance — applied only
 * after the "loan_change" approval workflow approves it.
 */
#[Fillable(['employee_loan_id', 'type', 'loan_instalment_id', 'new_instalment_count', 'new_start_date', 'reason', 'requested_by'])]
class LoanChange extends Model
{
    /** @use HasFactory<LoanChangeFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LoanChangeType::class,
            'status' => RequestStatus::class,
            'new_instalment_count' => 'integer',
            'new_start_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'loan.employee.branch_id',
            'department' => 'loan.employee.department_id',
            'employee_group' => 'loan.employee.groups.id',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }
}
