<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Enums\StageCategory;
use App\Models\JobApplication;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Illuminate\Support\Collection;

/**
 * Candidate pipeline: applications received in the period per vacancy, by the category of the
 * stage they are in now (open, interview, offer, hired, rejected, withdrawn).
 */
class CandidatePipelineReport extends RecruitmentReport
{
    public function key(): string
    {
        return 'candidate_pipeline';
    }

    public function columns(): array
    {
        $columns = ['vacancy' => Column::text($this->label('vacancy'))];

        foreach (StageCategory::cases() as $category) {
            $columns[$category->value] = Column::number($category->label());
        }

        return [...$columns, 'total' => Column::number($this->label('applications'))];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Collection
    {
        $query = JobApplication::query()
            ->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->leftJoin('recruitment_stages', 'recruitment_stages.id', '=', 'job_applications.stage_id')
            ->whereBetween('job_applications.applied_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->select('job_applications.vacancy_id', 'recruitment_stages.category')
            ->selectRaw('count(*) as applications')
            ->groupBy('job_applications.vacancy_id', 'recruitment_stages.category');

        $rows = $this->vacancyFilters($query, $filters)->toBase()->get();
        $vacancies = Vacancy::query()->withoutGlobalScopes()->whereKey($rows->pluck('vacancy_id')->unique()->all())->get()->keyBy('id');

        return $rows->groupBy('vacancy_id')->map(function (Collection $group, $vacancyId) use ($vacancies): array {
            $row = ['vacancy' => isset($vacancies[$vacancyId]) ? $vacancies[$vacancyId]->reference.' · '.$vacancies[$vacancyId]->title : '#'.$vacancyId];

            foreach (StageCategory::cases() as $category) {
                $row[$category->value] = (int) $group->where('category', $category->value)->sum('applications');
            }

            return [...$row, 'total' => (int) $group->sum('applications')];
        })->sortByDesc('total')->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $values = [];

        foreach (StageCategory::cases() as $category) {
            $values[$category->label()] = (int) $rows->sum($category->value);
        }

        return ChartData::series('bar', $this->label('applications'), $values);
    }
}
