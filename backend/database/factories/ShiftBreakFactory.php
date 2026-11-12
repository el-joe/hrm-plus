<?php

namespace Database\Factories;

use App\Enums\BreakType;
use App\Models\Shift;
use App\Models\ShiftBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An unpaid fixed 30-minute lunch break.
 *
 * @extends Factory<ShiftBreak>
 */
class ShiftBreakFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'name' => 'Lunch',
            'type' => BreakType::Fixed,
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'duration_minutes' => 30,
            'is_paid' => false,
        ];
    }
}
