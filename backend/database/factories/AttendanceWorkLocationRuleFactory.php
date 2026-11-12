<?php

namespace Database\Factories;

use App\Enums\WorkLocationScope;
use App\Models\AttendanceWorkLocationRule;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Lets one employee check in from anywhere.
 *
 * @extends Factory<AttendanceWorkLocationRule>
 */
class AttendanceWorkLocationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope_type' => WorkLocationScope::Employee,
            'scope_id' => Employee::factory(),
            'allow_anywhere' => true,
        ];
    }
}
