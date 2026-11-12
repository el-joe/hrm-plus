<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Reports\Column;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applications received in the period: candidate, vacancy, current stage and status, source,
 * average interview score; expected salary is a financial column.
 */
class ApplicationsReport extends RecruitmentReport
{
    public function key(): string
    {
        return 'applications';
    }

    public function extraFilters(): array
    {
        return [...parent::extraFilters(), 'application_status' => [
            'label' => __('reports.filters.application_status'),
            'options' => collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            'applied_at' => Column::date($this->label('applied_at')),
            'candidate' => Column::text($this->label('candidate')),
            'email' => Column::code($this->label('email')),
            'vacancy' => Column::text($this->label('vacancy')),
            'stage' => Column::text($this->label('stage')),
            'status' => Column::text($this->label('status')),
            'source' => Column::text($this->label('source')),
            'score' => Column::decimal($this->label('score'), 2, false),
            'expected_salary' => Column::money($this->label('expected_salary'), false),
        ];
    }

    public function sortable(): array
    {
        return ['applied_at' => 'job_applications.applied_at', 'score' => 'job_applications.average_score', 'status' => 'job_applications.status'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = JobApplication::query()
            ->with(['candidate', 'vacancy', 'stage'])
            ->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->select('job_applications.*')
            ->whereBetween('job_applications.applied_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->when($filters['application_status'] ?? null, fn (Builder $query, string $status) => $query->where('job_applications.status', $status))
            ->orderByDesc('job_applications.applied_at')->orderByDesc('job_applications.id');

        return $this->vacancyFilters($query, $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var JobApplication $record */
        return [
            'applied_at' => $record->applied_at,
            'candidate' => $record->candidate?->name,
            'email' => $record->candidate?->email,
            'vacancy' => $record->vacancy ? $record->vacancy->reference.' · '.$record->vacancy->title : null,
            'stage' => $record->stage?->name,
            'status' => $record->status?->label(),
            'source' => ($record->source ?? $record->candidate?->source)?->label(),
            'score' => $record->average_score === null ? null : (string) $record->average_score,
            'expected_salary' => $record->candidate?->getRawOriginal('expected_salary'),
        ];
    }
}
