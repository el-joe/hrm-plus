<?php

namespace Database\Factories;

use App\Enums\AttendanceScope;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Assigns a policy to the whole company.
 *
 * @extends Factory<AttendancePolicyAssignment>
 */
class AttendancePolicyAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_policy_id' => AttendancePolicy::factory(),
            'scope_type' => AttendanceScope::Company,
            'scope_id' => null,
            'effective_from' => '2020-01-01',
        ];
    }
}
