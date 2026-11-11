<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\BreakMode;
use App\Enums\MissingPunchAction;
use App\Models\AttendancePolicyVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation of the rule fields of an attendance-policy version.
 *
 * @mixin FormRequest
 */
trait PolicyRuleRules
{
    /**
     * Boolean rule fields (sent as hidden 0 + checkbox).
     *
     * @var list<string>
     */
    public static array $booleanRules = [
        'late_count_from_shift_start', 'overtime_enabled', 'overtime_requires_approval', 'overtime_count_early_arrival',
        'rest_day_work_is_overtime', 'manual_entry_requires_approval', 'correction_requires_approval',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function ruleRules(): array
    {
        $minutes = fn (bool $required, int $max) => [$required ? 'required' : 'nullable', 'integer', 'between:0,'.$max];
        $rate = ['required', 'decimal:0,2', 'between:1,10'];

        return [
            'late_grace_minutes' => $minutes(true, 240),
            'late_rounding_minutes' => $minutes(true, 120),
            'late_absent_after_minutes' => $minutes(false, 1440),
            'early_leave_grace_minutes' => $minutes(true, 240),
            'early_leave_rounding_minutes' => $minutes(true, 120),
            'min_worked_minutes_for_present' => $minutes(false, 1440),
            'missing_punch_action' => ['required', Rule::enum(MissingPunchAction::class)],
            'punch_dedupe_seconds' => $minutes(true, 3600),
            'overtime_min_minutes' => $minutes(true, 480),
            'overtime_rounding_minutes' => $minutes(true, 120),
            'overtime_max_daily_minutes' => $minutes(false, 1440),
            'overtime_rate_regular' => $rate,
            'overtime_rate_rest_day' => $rate,
            'overtime_rate_holiday' => $rate,
            'break_mode' => ['required', Rule::enum(BreakMode::class)],
            'max_break_minutes' => $minutes(false, 480),
            ...array_fill_keys(self::$booleanRules, ['sometimes', 'boolean']),
        ];
    }

    /**
     * Rule values ready for an AttendancePolicyVersion.
     *
     * @return array<string, mixed>
     */
    public function ruleValues(): array
    {
        $values = [];

        foreach (AttendancePolicyVersion::RULES as $rule) {
            $values[$rule] = in_array($rule, self::$booleanRules, true)
                ? $this->boolean($rule)
                : ($this->filled($rule) ? $this->input($rule) : null);
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('schedules.fields'), ...trans('schedules.policies.rule_labels')];
    }
}
