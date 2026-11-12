<?php

namespace App\Models;

use App\Enums\BreakMode;
use App\Enums\FaceVerificationMode;
use App\Enums\MissingPunchAction;
use App\Enums\MobileRuleAction;
use App\Models\Concerns\Auditable;
use BackedEnum;
use Database\Factories\AttendancePolicyVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The rules of an attendance policy from effective_from until the next
 * version. Versions are never edited once days were calculated with them; a
 * change is a new version (App\Actions\Attendance\SaveAttendancePolicy).
 */
#[Fillable([
    'version', 'effective_from',
    'late_grace_minutes', 'late_count_from_shift_start', 'late_rounding_minutes', 'late_absent_after_minutes',
    'early_leave_grace_minutes', 'early_leave_rounding_minutes', 'min_worked_minutes_for_present', 'missing_punch_action',
    'punch_dedupe_seconds', 'overtime_enabled', 'overtime_min_minutes', 'overtime_rounding_minutes', 'overtime_max_daily_minutes',
    'overtime_requires_approval', 'overtime_count_early_arrival', 'rest_day_work_is_overtime',
    'overtime_rate_regular', 'overtime_rate_rest_day', 'overtime_rate_holiday', 'break_mode', 'max_break_minutes',
    'manual_entry_requires_approval', 'correction_requires_approval', 'created_by',
    'mobile_checkin_enabled', 'gps_max_accuracy_meters', 'gps_accuracy_tolerance_meters', 'gps_checkout_extra_meters',
    'gps_max_time_drift_seconds', 'gps_mock_location_action', 'gps_outside_window_action', 'gps_repeated_attempts_threshold',
    'face_verification_mode', 'face_max_attempts', 'face_failure_action',
    'web_checkin_enabled', 'web_checkin_requires_geofence',
])]
class AttendancePolicyVersion extends Model
{
    /** @use HasFactory<AttendancePolicyVersionFactory> */
    use Auditable, HasFactory;

    /**
     * Rule columns (everything the engine reads), in form order.
     *
     * @var list<string>
     */
    public const RULES = [
        'late_grace_minutes', 'late_count_from_shift_start', 'late_rounding_minutes', 'late_absent_after_minutes',
        'early_leave_grace_minutes', 'early_leave_rounding_minutes', 'min_worked_minutes_for_present', 'missing_punch_action',
        'punch_dedupe_seconds', 'overtime_enabled', 'overtime_min_minutes', 'overtime_rounding_minutes', 'overtime_max_daily_minutes',
        'overtime_requires_approval', 'overtime_count_early_arrival', 'rest_day_work_is_overtime',
        'overtime_rate_regular', 'overtime_rate_rest_day', 'overtime_rate_holiday', 'break_mode', 'max_break_minutes',
        'manual_entry_requires_approval', 'correction_requires_approval',
    ];

    /**
     * Mobile / web-portal check-in and face verification rules (Phase 5b). Read by
     * App\Services\Attendance\Mobile\MobileCheckIn when an attempt arrives; NOT
     * part of the day calculation snapshot (rules()), so changing them never
     * changes a calculated day.
     *
     * @var list<string>
     */
    public const MOBILE_RULES = [
        'mobile_checkin_enabled', 'gps_max_accuracy_meters', 'gps_accuracy_tolerance_meters', 'gps_checkout_extra_meters',
        'gps_max_time_drift_seconds', 'gps_mock_location_action', 'gps_outside_window_action', 'gps_repeated_attempts_threshold',
        'face_verification_mode', 'face_max_attempts', 'face_failure_action',
        'web_checkin_enabled', 'web_checkin_requires_geofence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'date',
            'late_grace_minutes' => 'integer',
            'late_count_from_shift_start' => 'boolean',
            'late_rounding_minutes' => 'integer',
            'late_absent_after_minutes' => 'integer',
            'early_leave_grace_minutes' => 'integer',
            'early_leave_rounding_minutes' => 'integer',
            'min_worked_minutes_for_present' => 'integer',
            'missing_punch_action' => MissingPunchAction::class,
            'punch_dedupe_seconds' => 'integer',
            'overtime_enabled' => 'boolean',
            'overtime_min_minutes' => 'integer',
            'overtime_rounding_minutes' => 'integer',
            'overtime_max_daily_minutes' => 'integer',
            'overtime_requires_approval' => 'boolean',
            'overtime_count_early_arrival' => 'boolean',
            'rest_day_work_is_overtime' => 'boolean',
            'overtime_rate_regular' => 'decimal:2',
            'overtime_rate_rest_day' => 'decimal:2',
            'overtime_rate_holiday' => 'decimal:2',
            'break_mode' => BreakMode::class,
            'max_break_minutes' => 'integer',
            'manual_entry_requires_approval' => 'boolean',
            'correction_requires_approval' => 'boolean',
            'mobile_checkin_enabled' => 'boolean',
            'gps_max_accuracy_meters' => 'integer',
            'gps_accuracy_tolerance_meters' => 'integer',
            'gps_checkout_extra_meters' => 'integer',
            'gps_max_time_drift_seconds' => 'integer',
            'gps_mock_location_action' => MobileRuleAction::class,
            'gps_outside_window_action' => MobileRuleAction::class,
            'gps_repeated_attempts_threshold' => 'integer',
            'face_verification_mode' => FaceVerificationMode::class,
            'face_max_attempts' => 'integer',
            'face_failure_action' => MobileRuleAction::class,
            'web_checkin_enabled' => 'boolean',
            'web_checkin_requires_geofence' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AttendancePolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class, 'attendance_policy_id');
    }

    /**
     * The rule values as plain scalars (for the calculation snapshot).
     *
     * @return array<string, int|string|bool|null>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (self::RULES as $rule) {
            $value = $this->getAttribute($rule);
            $rules[$rule] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return $rules;
    }
}
