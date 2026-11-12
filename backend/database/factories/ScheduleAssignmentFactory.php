<?php

namespace Database\Factories;

use App\Enums\AttendanceScope;
use App\Models\ScheduleAssignment;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Assigns a work schedule to the whole company from 2025.
 *
 * @extends Factory<ScheduleAssignment>
 */
class ScheduleAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_schedule_id' => WorkSchedule::factory(),
            'scope_type' => AttendanceScope::Company,
            'scope_id' => null,
            'effective_from' => '2025-01-01',
        ];
    }
}
