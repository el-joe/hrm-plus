<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Time to hire for applications hired in the period: days from application to hire and from the
 * vacancy's publication to hire. The chart shows the average days per vacancy.
 */
class TimeToHireReport extends RecruitmentReport
{
    public function key(): string
    {
        return 'time_to_hire';
    }

    public function columns(): array
    {
        return [
            'candidate' => Column::text($this->label('candidate')),
            'vacancy' => Column::text($this->label('vacancy')),
            'published' => Column::date($this->label('publish_at')),
            'applied_at' => Column::date($this->label('applied_at')),
            'hired_at' => Column::date($this->label('hired_at')),
            'days_to_hire' => Column::number($this->label('days_to_hire'), false),
            'days_from_publish' => Column::number($this->label('days_from_publish'), false),
        ];
    }

    public function sortable(): array
    {
        return ['hired_at' => 'job_applications.hired_at', 'applied_at' => 'job_applications.applied_at'];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Builder
    {
        $query = JobApplication::query()
            ->with(['candidate', 'vacancy'])
            ->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->select('job_applications.*')
            ->where('job_applications.status', ApplicationStatus::Hired->value)
            ->whereBetween('job_applications.hired_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->orderBy('job_applications.hired_at')->orderBy('job_applications.id');

        return $this->vacancyFilters($query, $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var JobApplication $record */
        $hired = $record->hired_at ? CarbonImmutable::instance($record->hired_at)->startOfDay() : null;
        $published = $record->vacancy?->publish_at;

        return [
            'candidate' => $record->candidate?->name,
            'vacancy' => $record->vacancy ? $record->vacancy->reference.' · '.$record->vacancy->title : null,
            'published' => $published,
            'applied_at' => $record->applied_at,
            'hired_at' => $record->hired_at,
            'days_to_hire' => $hired && $record->applied_at ? (int) CarbonImmutable::instance($record->applied_at)->startOfDay()->diffInDays($hired) : null,
            'days_from_publish' => $hired && $published ? (int) CarbonImmutable::instance($published)->startOfDay()->diffInDays($hired) : null,
        ];
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $average = $rows->groupBy('vacancy')->map(fn (Collection $group): float => round((float) $group->avg('days_to_hire'), 1))->sortDesc()->take(12);

        return ChartData::series('bar', $this->label('days_to_hire'), $average->all(), ['horizontal' => true, 'decimals' => 1]);
    }
}
