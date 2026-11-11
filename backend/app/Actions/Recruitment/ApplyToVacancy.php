<?php

namespace App\Actions\Recruitment;

use App\Enums\ApplicationStatus;
use App\Enums\CandidateSource;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Recruitment\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Put a candidate into a vacancy's pipeline at its entry stage and record
 * the first history row. One application per candidate and vacancy.
 */
class ApplyToVacancy
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Candidate $candidate, Vacancy $vacancy, CandidateSource $source, ?string $coverLetter = null, ?User $actor = null): JobApplication
    {
        if (! $vacancy->acceptsApplications()) {
            throw ValidationException::withMessages(['vacancy_id' => __('recruitment.validation.vacancy_closed')]);
        }

        $stage = $this->pipeline->entryStage($vacancy);

        if ($stage === null) {
            throw ValidationException::withMessages(['vacancy_id' => __('recruitment.validation.no_pipeline')]);
        }

        return DB::transaction(function () use ($candidate, $vacancy, $source, $coverLetter, $actor, $stage): JobApplication {
            $exists = JobApplication::query()->withoutGlobalScopes()
                ->where('candidate_id', $candidate->getKey())->where('vacancy_id', $vacancy->getKey())
                ->lockForUpdate()->exists();

            if ($exists) {
                throw ValidationException::withMessages(['vacancy_id' => __('recruitment.validation.already_applied')]);
            }

            $application = JobApplication::create([
                'candidate_id' => $candidate->getKey(),
                'vacancy_id' => $vacancy->getKey(),
                'stage_id' => $stage->getKey(),
                'status' => ApplicationStatus::Active,
                'source' => $source,
                'cover_letter' => $coverLetter,
                'applied_at' => now(),
                'created_by' => $actor?->getKey(),
            ]);
            $application->forceFill(['stage_changed_at' => now()])->saveQuietly();

            $application->stageChanges()->create([
                'from_stage_id' => null,
                'to_stage_id' => $stage->getKey(),
                'to_stage_name' => $stage->name_en ?: $stage->name_ar,
                'note' => __('recruitment.history.applied', ['source' => $source->label()]),
                'changed_by' => $actor?->getKey(),
                'created_at' => now(),
            ]);

            return $application;
        });
    }
}
