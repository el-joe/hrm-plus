<?php

namespace App\Models;

use App\Enums\AttendanceDayStatus;
use App\Enums\OvertimeStatus;
use App\Models\Concerns\HasDataScope;
use Database\Factories\AttendanceDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The processed attendance of one employee on one date for one shift
 * (shift_key 0 = no shift: rest day, holiday or unscheduled work). Written
 * ONLY by App\Services\Attendance\AttendanceEngine. Inside a locked period the
 * row is frozen and differences are written to attendance_adjustments.
 *
 * Scoped by the branch / department the employee had on that date.
 */
class AttendanceDay extends Model
{
    /** @use HasFactory<AttendanceDayFactory> */
    use HasDataScope, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * Numbers compared / adjusted after a lock.
     *
     * @var list<string>
     */
    public const MINUTE_FIELDS = ['worked_minutes', 'late_minutes', 'early_leave_minutes', 'shortfall_minutes', 'break_minutes', 'overtime_minutes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'status' => AttendanceDayStatus::class,
            'overtime_status' => OvertimeStatus::class,
            'is_rest_day' => 'boolean',
            'is_manual' => 'boolean',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'first_in_at' => 'datetime',
            'last_out_at' => 'datetime',
            'scheduled_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'shortfall_minutes' => 'integer',
            'break_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'overtime_approved_minutes' => 'integer',
            'flags' => 'array',
            'sources' => 'array',
            'calculation' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'department_id',
            'employee_group' => 'employee.groups.id',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<AttendanceStatus, $this>
     */
    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    /**
     * @return BelongsTo<Holiday, $this>
     */
    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    /**
     * @return BelongsTo<AttendancePolicyVersion, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicyVersion::class, 'attendance_policy_version_id');
    }

    /**
     * @return BelongsTo<AttendancePeriodLock, $this>
     */
    public function lock(): BelongsTo
    {
        return $this->belongsTo(AttendancePeriodLock::class, 'attendance_period_lock_id');
    }

    /**
     * @return HasMany<AttendancePunch, $this>
     */
    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class)->orderBy('punched_at')->orderBy('id');
    }

    /**
     * @return HasMany<AttendanceAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class)->whereNull('superseded_at')->orderBy('id');
    }

    public function isLocked(): bool
    {
        return $this->attendance_period_lock_id !== null;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /**
     * Label of the status, using the configurable status name when a manual status applies.
     */
    public function statusLabel(): string
    {
        return $this->attendanceStatus?->name ?? $this->status->label();
    }
}
