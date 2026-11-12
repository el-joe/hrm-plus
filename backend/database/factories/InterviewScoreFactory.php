<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\InterviewScore;
use App\Models\InterviewScorecard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A score of 4 for one criterion on a scorecard.
 *
 * @extends Factory<InterviewScore>
 */
class InterviewScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_scorecard_id' => InterviewScorecard::factory(),
            'evaluation_criterion_id' => EvaluationCriterion::factory(),
            'score' => 4,
        ];
    }
}
