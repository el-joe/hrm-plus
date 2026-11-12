<?php

namespace App\Services\Reports\Definitions\Services;

use App\Enums\DisciplinaryCaseStatus;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Disciplinary cases (spec module 15; RESTRICTED, beyond the module 16 list): incidents in the
 * period with violation, severity, status and the latest action. Needs disciplinary.view; every
 * column is guarded "disciplinary", so each view and export is written to the sensitive-data
 * access log.
 */
class DisciplinaryCasesReport extends Report
{
    public function key(): string
    {
        return 'disciplinary_cases';
    }

    public function area(): string
    {
        return 'disciplinary';
    }

    public function requires(): array
    {
        return ['disciplinary.view'];
    }

    public function defaults(): array
    {
        return ['from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->format('Y-m-d')];
    }

    public function extraFilters(): array
    {
        return ['case_status' => ['label' => __('reports.filters.case_status'), 'options' => collect(DisciplinaryCaseStatus::cases())->mapWithKeys(fn (DisciplinaryCaseStatus $status): array => [$status->value => $status->label()])->all()]];
    }

    public function columns(): array
    {
        $guard = fn (Column $column): Column => $column->guardedBy('disciplinary');

        return array_map($guard, [
            'reference' => Column::code($this->label('reference')),
            'incident_date' => Column::date($this->label('incident_date')),
            ...$this->employeeColumns(),
            'violation' => Column::text($this->label('violation')),
            'severity' => Column::text($this->label('severity')),
            'status' => Column::text($this->label('status')),
            'latest_action' => Column::text($this->label('latest_action')),
            'action_status' => Column::text($this->label('action_status')),
        ]);
    }

    public function sortable(): array
    {
        return ['incident_date' => 'disciplinary_cases.incident_date', 'status' => 'disciplinary_cases.status', 'reference' => 'disciplinary_cases.reference'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = DisciplinaryCase::query()
            ->with(['employee', 'branch', 'department', 'violationType', 'latestAction'])
            ->whereBetween('disciplinary_cases.incident_date', [$filters['from'], $filters['to']])
            ->when($filters['case_status'] ?? null, fn (Builder $query, string $status) => $query->where('disciplinary_cases.status', $status))
            ->orderBy('disciplinary_cases.incident_date')->orderBy('disciplinary_cases.id');

        return ReportFilters::applyEmployeeFilters($query, 'disciplinary_cases.employee_id', $filters, ['branch' => 'disciplinary_cases.branch_id', 'department' => 'disciplinary_cases.department_id']);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var DisciplinaryCase $record */
        return [
            'reference' => $record->reference,
            'incident_date' => $record->incident_date,
            'employee_number' => $record->employee?->employee_number,
            'employee' => $record->employee?->name,
            'branch' => $record->branch?->name,
            'department' => $record->department?->name,
            'violation' => $record->violationType?->name,
            'severity' => $record->violationType?->severity?->label(),
            'status' => $record->status?->label(),
            'latest_action' => $record->latestAction?->type?->label(),
            'action_status' => $record->latestAction?->status?->label(),
        ];
    }
}
