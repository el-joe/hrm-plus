<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'code' => 'DEP-'.$number,
            'name_ar' => 'قسم '.$number,
            'name_en' => ucfirst(fake()->words(2, true)).' '.$number,
            'parent_id' => null,
            'branch_id' => null,
            'cost_center_id' => null,
            'head_position_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
