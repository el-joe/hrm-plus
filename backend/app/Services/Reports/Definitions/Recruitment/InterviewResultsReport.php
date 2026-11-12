<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Models\Interview;
use App\Models\User;
use App\Services\Reports\Column;
use Illuminate\Database\Eloquent\Builder;

/**
 * Interviews scheduled in the period with their outcome: status, average scorecard score and the
 * decision (advance / hold / reject).
 */
class InterviewResultsReport extends RecruitmentReport
{
    public function key(): string
    {
        return 'interview_results';
    }

    public function columns(): array
    {
        return [
            'scheduled_at' => Column::dateTime($this->label('scheduled_at')),
            'candidate' => Column::text($this->label('candidate')),
            'vacancy' => Column::text($this->label('vacancy')),
            'type' => Column::text($this->label('interview_type')),
            'mode' => Column::text($this->label('mode')),
            'status' => Column::text($this->label('status')),
            'score' => Column::decimal($this->label('score'), 2, false),
            'decision' => Column::text($this->label('decision')),
        ];
    }

    public function sortable(): array
    {
        return ['scheduled_at' => 'interviews.scheduled_at', 'score' => 'interviews.average_score', 'status' => 'interviews.status'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = Interview::query()
            ->with(['application.candidate', 'application.vacancy'])
            ->join('job_applications', 'job_applications.id', '=', 'interviews.job_application_id')
            ->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->select('interviews.*')
            ->whereBetween('interviews.scheduled_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->orderBy('interviews.scheduled_at')->orderBy('interviews.id');

        return $this->vacancyFilters($query, $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var Interview $record */
        $vacancy = $record->application?->vacancy;

        return [
            'scheduled_at' => $record->scheduled_at,
            'candidate' => $record->application?->candidate?->name,
            'vacancy' => $vacancy ? $vacancy->reference.' · '.$vacancy->title : null,
            'type' => $record->type?->label(),
            'mode' => $record->mode?->label(),
            'status' => $record->status?->label(),
            'score' => $record->average_score === null ? null : (string) $record->average_score,
            'decision' => $record->decision?->label(),
        ];
    }
}
