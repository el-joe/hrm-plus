<?php

namespace App\Models;

use App\Enums\LeaveTransactionType;
use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the leave balance ledger. Rows are never updated or deleted:
 * a correction is a new row (adjustment, reversal). Written only through
 * App\Services\Leave\LeaveLedger.
 */
#[Fillable([
    'employee_id', 'leave_type_id', 'leave_year', 'type', 'amount', 'effective_date', 'expires_on', 'leave_request_id',
    'leave_policy_id', 'reference', 'reason', 'created_by',
])]
class LeaveBalanceTransaction extends Model
{
    use ScopedThroughEmployee;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_year' => 'integer',
            'type' => LeaveTransactionType::class,
            'amount' => 'decimal:2',
            'effective_date' => 'date',
            'expires_on' => 'date',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return BelongsTo<LeavePolicy, $this>
     */
    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
