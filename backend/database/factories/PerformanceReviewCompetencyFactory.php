<?php

namespace Database\Factories;

use App\Models\Competency;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCompetency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An unrated competency weighted 20 % in a review.
 *
 * @extends Factory<PerformanceReviewCompetency>
 */
class PerformanceReviewCompetencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_review_id' => PerformanceReview::factory(),
            'competency_id' => Competency::factory(),
            'weight' => '20.00',
        ];
    }
}
