<?php

namespace Database\Factories;

use App\Enums\ShiftType;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * A fixed 08:00–16:00 day shift without breaks.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'S'.fake()->unique()->numberBetween(100, 99999),
            'name_ar' => 'وردية',
            'name_en' => 'Shift '.fake()->word(),
            'type' => ShiftType::Fixed,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'checkin_opens_before_minutes' => 120,
            'checkout_closes_after_minutes' => 240,
            'is_night' => false,
            'color' => 'chart-1',
            'is_active' => true,
        ];
    }

    public function between(string $start, string $end): static
    {
        return $this->state(['start_time' => $start, 'end_time' => $end]);
    }

    /**
     * Flexible: a window, required minutes and optional core hours.
     */
    public function flexible(string $windowStart, string $windowEnd, int $requiredMinutes, ?string $coreStart = null, ?string $coreEnd = null): static
    {
        return $this->state([
            'type' => ShiftType::Flexible, 'start_time' => $windowStart, 'end_time' => $windowEnd,
            'required_minutes' => $requiredMinutes, 'core_start_time' => $coreStart, 'core_end_time' => $coreEnd,
        ]);
    }
}
