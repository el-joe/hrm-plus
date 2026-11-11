<?php

namespace Database\Factories;

use App\Models\EmployeeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeGroup>
 */
class EmployeeGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'GRP-'.$number,
            'name_ar' => 'مجموعة '.$number,
            'name_en' => ucfirst(fake()->words(2, true)).' '.$number,
            'is_active' => true,
        ];
    }
}
