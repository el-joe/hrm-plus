<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PerformanceGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceGoal>
 */
class PerformanceGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => 'goal',
            'title' => fake()->sentence(4),
            'weight' => '50.00',
            'progress' => '0.00',
            'status' => 'active',
        ];
    }
}
