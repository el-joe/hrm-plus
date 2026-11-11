<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\ScheduleChangeType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request to change one employee's shift on a date (shift_id null = day
 * off) or to swap the shifts of two employees on a date. Decided through the
 * approval engine (workflow "schedule_change"); approval writes
 * schedule_overrides (App\Actions\Attendance\ManageScheduleChange).
 */
#[Fillable(['type', 'employee_id', 'work_date', 'shift_id', 'swap_employee_id', 'reason', 'requested_by'])]
class ScheduleChangeRequest extends Model
{
    use Auditable, HasApprovals, ScopedThroughEmployee;

    public const WORKFLOW = 'schedule_change';

    /**
     * Column defaults, so a new instance knows its status before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ScheduleChangeType::class,
            'status' => RequestStatus::class,
            'work_date' => 'date',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<Employee, $this>
     */
    public function swapEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'swap_employee_id');
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<ScheduleOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(ScheduleOverride::class);
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }
}
