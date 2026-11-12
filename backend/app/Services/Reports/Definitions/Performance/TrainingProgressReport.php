<?php

namespace App\Services\Reports\Definitions\Performance;

use App\Enums\TrainingEnrolmentStatus;
use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Training progress (listed under performance in the spec): enrolments in sessions starting in the
 * period — status, attendance rate (TrainingEnrolment::attendanceRate), score, completion date and
 * cost (DECIMAL snapshot). Grouped by the enrolment's branch / department
 * snapshot.
 */
class TrainingProgressReport extends Report
{
    public function key(): string
    {
        return 'training_progress';
    }

    public function area(): string
    {
        return 'performance';
    }

    public function areaPermissions(): array
    {
        return ['training.view'];
    }

    public function defaults(): array
    {
        return ['from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->endOfYear()->format('Y-m-d')];
    }

    public function extraFilters(): array
    {
        return ['enrolment_status' => [
            'label' => __('reports.filters.enrolment_status'),
            'options' => collect(TrainingEnrolmentStatus::cases())->mapWithKeys(fn (TrainingEnrolmentStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'course' => Column::text($this->label('course')),
            'session' => Column::code($this->label('session')),
            'start_date' => Column::date($this->label('start_date')),
            'end_date' => Column::date($this->label('end_date')),
            'status' => Column::text($this->label('status')),
            'attendance_rate' => Column::percent($this->label('attendance_rate'), 0),
            'score' => Column::decimal($this->label('score'), 2, false),
            'completed_at' => Column::date($this->label('completed_at')),
            'cost' => Column::amount($this->label('cost')),
        ];
    }

    public function sortable(): array
    {
        return ['start_date' => 'training_sessions.start_date', 'status' => 'training_enrolments.status', 'score' => 'training_enrolments.score'];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Builder
    {
        $query = TrainingEnrolment::query()
            ->with(['employee', 'session.course', 'attendances'])
            ->join('training_sessions', 'training_sessions.id', '=', 'training_enrolments.training_session_id')
            ->leftJoin('branches', 'branches.id', '=', 'training_enrolments.branch_id')
            ->leftJoin('departments', 'departments.id', '=', 'training_enrolments.department_id')
            ->select('training_enrolments.*', 'branches.name_ar as branch_ar', 'branches.name_en as branch_en', 'departments.name_ar as department_ar', 'departments.name_en as department_en')
            ->whereBetween('training_sessions.start_date', [$filters['from'], $filters['to']])
            ->when($filters['enrolment_status'] ?? null, fn (Builder $query, string $status) => $query->where('training_enrolments.status', $status))
            ->orderBy('training_sessions.start_date')->orderBy('training_enrolments.id');

        return ReportFilters::applyEmployeeFilters($query, 'training_enrolments.employee_id', $filters, [
            'branch' => 'training_enrolments.branch_id', 'department' => 'training_enrolments.department_id',
        ]);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var TrainingEnrolment $record */
        $en = app()->getLocale() === 'en';
        $attended = in_array($record->status, [TrainingEnrolmentStatus::Enrolled, TrainingEnrolmentStatus::Completed, TrainingEnrolmentStatus::Failed, TrainingEnrolmentStatus::NoShow], true);

        return [
            'employee_number' => $record->employee?->employee_number,
            'employee' => $record->employee?->name,
            'branch' => $en ? $record->getAttribute('branch_en') : $record->getAttribute('branch_ar'),
            'department' => $en ? $record->getAttribute('department_en') : $record->getAttribute('department_ar'),
            'course' => $record->session?->course?->name,
            'session' => $record->session?->reference,
            'start_date' => $record->session?->start_date,
            'end_date' => $record->session?->end_date,
            'status' => $record->status?->label(),
            'attendance_rate' => $attended && $record->session ? $record->attendanceRate() : null,
            'score' => $record->score === null ? null : (string) $record->score,
            'completed_at' => $record->completed_at,
            'cost' => $record->cost === null ? null : (string) $record->getRawOriginal('cost'),
        ];
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        return ChartData::series('doughnut', __('reports.columns.enrolments'), $rows->groupBy('status')->map->count()->all());
    }
}
