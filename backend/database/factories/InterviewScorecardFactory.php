<?php

namespace Database\Factories;

use App\Enums\ScorecardRecommendation;
use App\Models\Interview;
use App\Models\InterviewScorecard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An interviewer's submitted "yes" scorecard.
 *
 * @extends Factory<InterviewScorecard>
 */
class InterviewScorecardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_id' => Interview::factory(),
            'user_id' => User::factory()->admin(),
            'recommendation' => ScorecardRecommendation::Yes,
            'overall_score' => '4.00',
            'submitted_at' => now(),
        ];
    }
}
