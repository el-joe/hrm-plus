<?php

namespace App\Models;

use App\Enums\FaceVerificationMode;
use App\Enums\MobileAttemptOutcome;
use App\Enums\PunchDirection;
use App\Enums\ReviewStatus;
use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Every mobile GPS check-in / check-out attempt with the location data the
 * app sent and the server's decision: accepted (a raw event was recorded),
 * rejected (nothing recorded) or flagged (recorded only if a reviewer
 * approves it). Written by App\Services\Attendance\Mobile\MobileCheckIn and
 * MobileAttemptReview only.
 */
class AttendanceMobileAttempt extends Model
{
    use HasDataScope;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => PunchDirection::class,
            'outcome' => MobileAttemptOutcome::class,
            'review_status' => ReviewStatus::class,
            'face_mode' => FaceVerificationMode::class,
            'reasons' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'decimal:2',
            'distance_meters' => 'integer',
            'allowed_radius_meters' => 'integer',
            'within_geofence' => 'boolean',
            'allow_anywhere' => 'boolean',
            'is_mock_location' => 'boolean',
            'within_shift_window' => 'boolean',
            'device_time' => 'datetime',
            'server_time' => 'datetime',
            'time_drift_seconds' => 'integer',
            'work_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'employee.branch_id',
            'department' => 'employee.department_id',
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
     * @return BelongsTo<BranchLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(BranchLocation::class, 'branch_location_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<AttendanceRawEvent, $this>
     */
    public function rawEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceRawEvent::class, 'attendance_raw_event_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<FaceVerification, $this>
     */
    public function faceVerifications(): HasMany
    {
        return $this->hasMany(FaceVerification::class)->orderBy('id');
    }

    /**
     * @return HasOne<FaceVerification, $this>
     */
    public function faceVerification(): HasOne
    {
        return $this->hasOne(FaceVerification::class)->latestOfMany();
    }

    public function isPendingReview(): bool
    {
        return $this->outcome === MobileAttemptOutcome::Flagged && $this->review_status === ReviewStatus::Pending;
    }

    /**
     * @return list<string>
     */
    public function reasonCodes(): array
    {
        return array_values($this->reasons ?? []);
    }
}
