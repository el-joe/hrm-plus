<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A recruiter's note on a candidate.
 *
 * @extends Factory<CandidateNote>
 */
class CandidateNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
