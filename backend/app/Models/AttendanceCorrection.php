<?php

namespace App\Models;

use App\Enums\CorrectionSource;
use App\Enums\CorrectionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\AttendanceCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A correction of one employee's day: requested check-in / check-out, a day
 * status, and / or punches to void. Raised by an admin, in the portal or via
 * the API; goes through the approval engine (workflow
 * "attendance_correction") when the policy requires it. Applying writes
 * manual raw events (never edits existing ones) and reprocesses the day.
 */
#[Fillable([
    'employee_id', 'work_date', 'shift_id', 'check_in_at', 'check_out_at', 'attendance_status_id', 'void_raw_event_ids',
    'reason', 'source', 'requested_by',
])]
class AttendanceCorrection extends Model
{
    /** @use HasFactory<AttendanceCorrectionFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    public const WORKFLOW = 'attendance_correction';

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
            'source' => CorrectionSource::class,
            'status' => CorrectionStatus::class,
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'void_raw_event_ids' => 'array',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<AttendanceStatus, $this>
     */
    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return MorphMany<AttendanceRawEvent, $this>
     */
    public function rawEvents(): MorphMany
    {
        return $this->morphMany(AttendanceRawEvent::class, 'origin');
    }

    public function isPending(): bool
    {
        return $this->status === CorrectionStatus::Pending;
    }
}
