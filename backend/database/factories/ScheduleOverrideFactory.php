<?php

namespace Database\Factories;

use App\Enums\OverrideSource;
use App\Models\Employee;
use App\Models\ScheduleOverride;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A manual shift override for one day (the shift key is set by the model).
 *
 * @extends Factory<ScheduleOverride>
 */
class ScheduleOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => now()->addWeek()->toDateString(),
            'shift_id' => Shift::factory(),
            'source' => OverrideSource::Manual,
        ];
    }
}
