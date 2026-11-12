<?php

namespace Database\Factories;

use App\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A processed present day of 8 hours, written directly (no engine run).
 *
 * @extends Factory<AttendanceDay>
 */
class AttendanceDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => fake()->dateTimeBetween('-3 months', '-1 day')->format('Y-m-d'),
            'shift_key' => 0,
            'branch_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->branch_id,
            'department_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->department_id,
            'timezone' => 'Africa/Cairo',
            'status' => AttendanceDayStatus::Present,
            'scheduled_minutes' => 480,
            'worked_minutes' => 480,
        ];
    }
}
