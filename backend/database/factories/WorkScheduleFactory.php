<?php

namespace Database\Factories;

use App\Enums\ScheduleType;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active weekly schedule without days (see weeklySchedule() for one with shifts).
 *
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 9_999_999);

        return [
            'code' => 'WS'.$number,
            'name_ar' => 'جدول '.$number,
            'name_en' => 'Schedule '.$number,
            'type' => ScheduleType::Weekly,
            'is_active' => true,
        ];
    }
}
