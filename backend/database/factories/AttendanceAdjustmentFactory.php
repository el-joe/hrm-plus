<?php

namespace Database\Factories;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An HR adjustment turning an absent day into a present one.
 *
 * @extends Factory<AttendanceAdjustment>
 */
class AttendanceAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_day_id' => AttendanceDay::factory(),
            'employee_id' => fn (array $attributes) => AttendanceDay::query()->findOrFail($attributes['attendance_day_id'])->employee_id,
            'work_date' => fn (array $attributes) => AttendanceDay::query()->findOrFail($attributes['attendance_day_id'])->work_date->toDateString(),
            'status_before' => 'absent',
            'status_after' => 'present',
            'worked_minutes_delta' => 480,
            'reason' => fake()->sentence(),
            'calculation_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
