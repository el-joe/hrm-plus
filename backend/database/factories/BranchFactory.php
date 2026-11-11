<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
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
            'code' => 'BR-'.$number,
            'name_ar' => 'فرع '.$number,
            'name_en' => ucfirst(fake()->words(2, true)).' '.$number,
            'is_headquarters' => false,
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+20 1## ### ####'),
            'country_code' => 'EG',
            'address_ar' => null,
            'address_en' => fake()->address(),
            'timezone' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
