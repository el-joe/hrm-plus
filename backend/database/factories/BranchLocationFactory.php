<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchLocation>
 */
class BranchLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name_ar' => 'المبنى الرئيسي',
            'name_en' => 'Main building',
            'address' => fake()->address(),
            'latitude' => (string) fake()->latitude(22, 31),
            'longitude' => (string) fake()->longitude(25, 34),
            'radius_meters' => 150,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
