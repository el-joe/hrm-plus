<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare review row (prefer launching a cycle through ManagePerformanceCycle in tests).
 *
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'PRV-T-'.fake()->unique()->numberBetween(1, 9_999_999),
            'performance_cycle_id' => PerformanceCycle::factory(),
            'employee_id' => Employee::factory(),
            'status' => 'self_assessment',
        ];
    }
}
