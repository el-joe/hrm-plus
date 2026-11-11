<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use App\Services\Recruitment\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Applications at the vacancy's entry stage (the default pipeline must exist:
 * seed RecruitmentDefaultsSeeder). Prefer App\Actions\Recruitment\ApplyToVacancy
 * when the stage history matters.
 *
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'vacancy_id' => Vacancy::factory(),
            'stage_id' => fn (array $attributes) => app(Pipeline::class)->entryStage(Vacancy::query()->withoutGlobalScopes()->findOrFail($attributes['vacancy_id']))?->id
                ?? RecruitmentStage::query()->firstOrFail()->id,
            'status' => ApplicationStatus::Active,
            'source' => CandidateSource::LinkedIn,
            'applied_at' => now(),
        ];
    }
}
