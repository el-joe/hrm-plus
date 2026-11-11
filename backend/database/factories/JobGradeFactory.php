<?php

namespace Database\Factories;

use App\Models\JobGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobGrade>
 */
class JobGradeFactory extends Factory
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
            'code' => 'GR-'.$number,
            'name_ar' => 'درجة '.$number,
            'name_en' => ucfirst(fake()->words(2, true)).' '.$number,
            'level' => fake()->numberBetween(1, 20),
            'min_salary' => '5000.00',
            'max_salary' => '9000.00',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
