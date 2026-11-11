<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
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
            'code' => 'POS-'.$number,
            'name_ar' => 'وظيفة '.$number,
            'name_en' => ucfirst(fake()->words(2, true)).' '.$number,
            'department_id' => Department::factory(),
            'job_title_id' => JobTitle::factory(),
            'job_grade_id' => null,
            'reports_to_position_id' => null,
            'headcount' => fake()->numberBetween(1, 5),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
