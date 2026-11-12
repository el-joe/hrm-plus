<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Enums\CandidateSource;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Illuminate\Support\Collection;

/**
 * Hiring sources: applications received in the period per source (the application's source, else
 * the candidate's), how many reached an interview, how many were hired, and the hire rate.
 */
class HiringSourcesReport extends RecruitmentReport
{
    use ConversionQueries;

    public function key(): string
    {
        return 'hiring_sources';
    }

    public function columns(): array
    {
        return [
            'source' => Column::text($this->label('source')),
            'applications' => Column::number($this->label('applications')),
            'interviewed' => Column::number($this->label('reached_interview')),
            'hired' => Column::number($this->label('hired')),
            'hire_rate' => Column::percent($this->label('hire_rate')),
        ];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Collection
    {
        $query = JobApplication::query()
            ->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->leftJoin('candidates', 'candidates.id', '=', 'job_applications.candidate_id')
            ->whereBetween('job_applications.applied_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->selectRaw('coalesce(job_applications.source, candidates.source) as source_key, count(*) as applications')
            ->selectRaw('sum(case when '.$this->reachedInterview().' then 1 else 0 end) as interviewed')
            ->selectRaw("sum(case when job_applications.status = 'hired' then 1 else 0 end) as hired")
            ->groupByRaw('coalesce(job_applications.source, candidates.source)');

        return $this->vacancyFilters($query, $filters)->toBase()->get()->map(fn (object $row): array => [
            'source' => CandidateSource::tryFrom((string) $row->source_key)?->label() ?? __('reports.ui.not_set'),
            'applications' => (int) $row->applications,
            'interviewed' => (int) $row->interviewed,
            'hired' => (int) $row->hired,
            'hire_rate' => $row->applications > 0 ? round($row->hired * 100 / $row->applications, 1) : 0,
        ])->sortByDesc('applications')->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        return ChartData::make('bar', $rows->pluck('source')->all(), [
            ['label' => $this->label('applications'), 'data' => $rows->pluck('applications')->all(), 'color' => 'chart-1'],
            ['label' => $this->label('hired'), 'data' => $rows->pluck('hired')->all(), 'color' => 'chart-2'],
        ]);
    }
}
