<?php

namespace App\Models;

use App\Enums\LeaveDayPart;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A leave request of one employee. Its days (leave_request_days) carry the
 * amount charged per date; approval runs through the approval engine
 * (workflow of the leave type, cancellations through "leave_cancellation").
 * Raised in the portal, the mobile API or by an admin on the employee's behalf.
 */
#[Fillable([
    'employee_id', 'leave_type_id', 'start_date', 'end_date', 'day_part', 'hours', 'start_time', 'days', 'reason',
    'source', 'requested_by',
])]
class LeaveRequest extends Model
{
    use Auditable, HasApprovals, ScopedThroughEmployee;

    public const CANCELLATION_WORKFLOW = 'leave_cancellation';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending', 'day_part' => 'full'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'day_part' => LeaveDayPart::class,
            'hours' => 'decimal:2',
            'days' => 'decimal:2',
            'status' => LeaveRequestStatus::class,
            'source' => LeaveRequestSource::class,
            'decided_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
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

    /**
     * @return HasMany<LeaveRequestDay, $this>
     */
    public function requestDays(): HasMany
    {
        return $this->hasMany(LeaveRequestDay::class)->orderBy('date');
    }

    /**
     * @return HasMany<LeaveRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class)->orderBy('id');
    }

    /**
     * Requests overlapping the date range (inclusive).
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from);
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<LeaveRequestStatus>  $statuses
     */
    public function scopeInStatus(Builder $query, array $statuses): void
    {
        $query->whereIn($query->qualifyColumn('status'), array_map(fn (LeaveRequestStatus $status): string => $status->value, $statuses));
    }

    public function isPending(): bool
    {
        return $this->status === LeaveRequestStatus::Pending;
    }

    public function isTaken(): bool
    {
        return $this->status->isTaken();
    }

    public function hasStarted(?string $today = null): bool
    {
        return $this->start_date->format('Y-m-d') <= ($today ?? now()->toDateString());
    }

    /**
     * "2026-11-02 → 2026-11-05" or "2026-11-02".
     */
    public function dateRange(): string
    {
        return $this->start_date->equalTo($this->end_date)
            ? $this->start_date->format('Y-m-d')
            : $this->start_date->format('Y-m-d').' → '.$this->end_date->format('Y-m-d');
    }

    /**
     * The partial-day label ("First half", "2 h") or null for whole days.
     */
    public function partLabel(): ?string
    {
        return match (true) {
            $this->day_part === LeaveDayPart::Hours => __('leave.common.hours_count', ['hours' => rtrim(rtrim((string) $this->hours, '0'), '.')]),
            $this->day_part->isHalf() => $this->day_part->label(),
            default => null,
        };
    }

    /**
     * "2026-11-02 → 2026-11-05" or "2026-11-02 (first half)".
     */
    public function period(): string
    {
        $period = $this->dateRange();

        $part = $this->partLabel();

        return $part === null ? $period : $period.' ('.$part.')';
    }
}
