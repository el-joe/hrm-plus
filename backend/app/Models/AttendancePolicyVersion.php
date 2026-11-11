<?php

namespace App\Models;

use App\Enums\BreakMode;
use App\Enums\MissingPunchAction;
use App\Models\Concerns\Auditable;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
])]
class AttendancePolicyVersion extends Model
{
    use Auditable;

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
