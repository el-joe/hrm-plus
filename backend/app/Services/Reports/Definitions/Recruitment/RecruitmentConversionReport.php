<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Models\JobApplication;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Illuminate\Support\Collection;

/**
 * Recruitment conversion funnel per vacancy for applications received in the period:
 * applications → reached interview → reached offer → hired, with the rates between them.
 */
class RecruitmentConversionReport extends RecruitmentReport
{
    use ConversionQueries;

    public function key(): string
    {
        return 'recruitment_conversion';
    }

    public function columns(): array
    {
        return [
            'vacancy' => Column::text($this->label('vacancy')),
            'applications' => Column::number($this->label('applications')),
            'interviewed' => Column::number($this->label('reached_interview')),
            'offered' => Column::number($this->label('reached_offer')),
            'hired' => Column::number($this->label('hired')),
            'interview_rate' => Column::percent($this->label('interview_rate')),
            'offer_rate' => Column::percent($this->label('offer_rate')),
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
            ->whereBetween('job_applications.applied_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->select('job_applications.vacancy_id')
            ->selectRaw('count(*) as applications')
            ->selectRaw('sum(case when '.$this->reachedInterview().' then 1 else 0 end) as interviewed')
            ->selectRaw('sum(case when '.$this->reachedOffer().' then 1 else 0 end) as offered')
            ->selectRaw("sum(case when job_applications.status = 'hired' then 1 else 0 end) as hired")
            ->groupBy('job_applications.vacancy_id');

        $rows = $this->vacancyFilters($query, $filters)->toBase()->get();
        $vacancies = Vacancy::query()->withoutGlobalScopes()->whereKey($rows->pluck('vacancy_id')->all())->get()->keyBy('id');
        $rate = fn (int $part, int $whole): float => $whole > 0 ? round($part * 100 / $whole, 1) : 0.0;

        return $rows->map(fn (object $row): array => [
            'vacancy' => isset($vacancies[$row->vacancy_id]) ? $vacancies[$row->vacancy_id]->reference.' · '.$vacancies[$row->vacancy_id]->title : '#'.$row->vacancy_id,
            'applications' => (int) $row->applications,
            'interviewed' => (int) $row->interviewed,
            'offered' => (int) $row->offered,
            'hired' => (int) $row->hired,
            'interview_rate' => $rate((int) $row->interviewed, (int) $row->applications),
            'offer_rate' => $rate((int) $row->offered, (int) $row->interviewed),
            'hire_rate' => $rate((int) $row->hired, (int) $row->applications),
        ])->sortByDesc('applications')->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        return ChartData::series('bar', $this->label('applications'), [
            $this->label('applications') => (int) $rows->sum('applications'),
            $this->label('reached_interview') => (int) $rows->sum('interviewed'),
            $this->label('reached_offer') => (int) $rows->sum('offered'),
            $this->label('hired') => (int) $rows->sum('hired'),
        ], ['horizontal' => true]);
    }
}
