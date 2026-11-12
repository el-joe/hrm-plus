<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A shift on Sunday (day 0) of a weekly schedule.
 *
 * @extends Factory<WorkScheduleDay>
 */
class WorkScheduleDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_schedule_id' => WorkSchedule::factory(),
            'day_index' => 0,
            'shift_id' => Shift::factory(),
        ];
    }
}
