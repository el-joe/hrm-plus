<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Enums\ApplicationStatus;
use App\Enums\VacancyStatus;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Reports\Column;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vacancies open at some point of the period (created on or before its end, not closed before its
 * start): openings, filled, applications, hires; the salary range is a financial column.
 */
class VacanciesReport extends RecruitmentReport
{
    public function key(): string
    {
        return 'vacancies';
    }

    public function extraFilters(): array
    {
        return ['vacancy_status' => [
            'label' => __('reports.filters.vacancy_status'),
            'options' => collect(VacancyStatus::cases())->mapWithKeys(fn (VacancyStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            'reference' => Column::code($this->label('reference')),
            'vacancy' => Column::text($this->label('vacancy')),
            'branch' => Column::text($this->label('branch')),
            'department' => Column::text($this->label('department')),
            'status' => Column::text($this->label('status')),
            'publish_at' => Column::date($this->label('publish_at')),
            'deadline' => Column::date($this->label('deadline')),
            'openings' => Column::number($this->label('openings')),
            'filled' => Column::number($this->label('filled')),
            'applications' => Column::number($this->label('applications')),
            'active_applications' => Column::number($this->label('active_applications')),
            'hired' => Column::number($this->label('hired')),
            'salary_range' => Column::text($this->label('salary_range'))->guardedBy('financial'),
        ];
    }

    public function sortable(): array
    {
        return ['reference' => 'vacancies.reference', 'publish_at' => 'vacancies.publish_at', 'applications' => 'applications_count', 'status' => 'vacancies.status'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = Vacancy::query()
            ->with(['branch', 'department'])
            ->withCount(['applications', 'applications as active_applications_count' => fn (Builder $query) => $query->where('status', ApplicationStatus::Active->value),
                'applications as hired_count' => fn (Builder $query) => $query->where('status', ApplicationStatus::Hired->value)])
            ->where('vacancies.created_at', '<=', $filters['to'].' 23:59:59')
            ->where(fn (Builder $query) => $query->whereNull('vacancies.closed_at')->orWhere('vacancies.closed_at', '>=', $filters['from']))
            ->when($filters['vacancy_status'] ?? null, fn (Builder $query, string $status) => $query->where('vacancies.status', $status))
            ->orderByDesc('vacancies.id');

        return $this->vacancyFilters($query, $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var Vacancy $record */
        $min = $record->getRawOriginal('salary_min');
        $max = $record->getRawOriginal('salary_max');

        return [
            'reference' => $record->reference,
            'vacancy' => $record->title,
            'branch' => $record->branch?->name,
            'department' => $record->department?->name,
            'status' => $record->status?->label(),
            'publish_at' => $record->publish_at,
            'deadline' => $record->application_deadline,
            'openings' => (int) $record->openings,
            'filled' => (int) $record->filled_count,
            'applications' => (int) $record->applications_count,
            'active_applications' => (int) $record->active_applications_count,
            'hired' => (int) $record->hired_count,
            'salary_range' => $min === null && $max === null ? null : trim(Money::format($min).' – '.Money::format($max).' '.$record->currency),
        ];
    }
}
