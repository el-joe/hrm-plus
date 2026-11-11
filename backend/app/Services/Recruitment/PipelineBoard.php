<?php

namespace App\Services\Recruitment;

use App\Models\JobApplication;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use Illuminate\Support\Collection;

/**
 * Data of the pipeline board / list of a vacancy: the stages in order with
 * their applications (candidate, interview count, latest offer status).
 * Applications sitting in a stage that is no longer active are shown in it
 * anyway, so nobody disappears from the board.
 */
class PipelineBoard
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return Collection<int, array{stage: RecruitmentStage, applications: Collection<int, JobApplication>}>
     */
    public function columns(Vacancy $vacancy, array $filters = []): Collection
    {
        $applications = $vacancy->applications()
            ->with(['candidate.tags', 'latestOffer'])
            ->withCount('interviews')
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->whereHas('candidate', fn ($query) => $query->search($term)))
            ->orderBy('stage_changed_at')
            ->get()
            ->groupBy('stage_id');

        $stages = $this->pipeline->stagesFor($vacancy, activeOnly: false)
            ->filter(fn (RecruitmentStage $stage): bool => $stage->is_active || $applications->has($stage->getKey()));

        return $stages->values()->map(fn (RecruitmentStage $stage): array => [
            'stage' => $stage,
            'applications' => $applications->get($stage->getKey(), collect()),
        ]);
    }
}
