<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF on a candidate's profile.
 *
 * @extends Factory<CandidateAttachment>
 */
class CandidateAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'title' => 'Portfolio',
            'file_path' => 'candidates/'.fake()->uuid().'.pdf',
            'file_name' => 'portfolio.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
