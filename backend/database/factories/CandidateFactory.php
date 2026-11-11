<?php

namespace Database\Factories;

use App\Enums\CandidateSource;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'مرشح '.fake()->numerify('###'),
            'name_en' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2011'.fake()->unique()->numerify('########'),
            'current_job_title' => fake()->jobTitle(),
            'experience_years' => '3.0',
            'source' => CandidateSource::LinkedIn,
            'locale' => 'en',
        ];
    }
}
