<?php

namespace Database\Factories;

use App\Enums\BreakMode;
use App\Enums\MissingPunchAction;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Version 1 of a policy: 10-minute graces, overtime from 30 minutes at 1.5 / 2 / 2.
 *
 * @extends Factory<AttendancePolicyVersion>
 */
class AttendancePolicyVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_policy_id' => AttendancePolicy::factory(),
            'version' => 1,
            'effective_from' => '2020-01-01',
            'late_grace_minutes' => 10,
            'early_leave_grace_minutes' => 10,
            'missing_punch_action' => MissingPunchAction::Incomplete,
            'overtime_min_minutes' => 30,
            'overtime_rate_regular' => '1.50',
            'overtime_rate_rest_day' => '2.00',
            'overtime_rate_holiday' => '2.00',
            'break_mode' => BreakMode::Scheduled,
        ];
    }
}
