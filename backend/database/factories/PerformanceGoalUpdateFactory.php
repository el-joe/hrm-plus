<?php

namespace Database\Factories;

use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A progress update (50 %) on a goal.
 *
 * @extends Factory<PerformanceGoalUpdate>
 */
class PerformanceGoalUpdateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_goal_id' => PerformanceGoal::factory(),
            'progress' => '50.00',
            'note' => fake()->sentence(),
        ];
    }
}
