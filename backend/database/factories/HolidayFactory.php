<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A one-day paid company holiday.
 *
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d');

        return [
            'name_ar' => 'عطلة',
            'name_en' => 'Holiday '.fake()->unique()->numberBetween(1, 999_999),
            'start_date' => $date,
            'end_date' => $date,
            'is_recurring' => false,
            'is_paid' => true,
            'is_active' => true,
        ];
    }
}
