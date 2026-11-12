<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Enums\AssignmentChangeType;
use App\Models\EmployeeAssignment;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employment history: every applied assignment change (hire, transfer, promotion, manager or status
 * change, termination, rehire) effective in the period, with the placement it set.
 */
class EmployeeHistoryReport extends Report
{
    public function key(): string
    {
        return 'employee_history';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function extraFilters(): array
    {
        return ['change_type' => [
            'label' => __('reports.filters.change_type'),
            'options' => collect(AssignmentChangeType::cases())->mapWithKeys(fn ($type): array => [$type->value => $type->label()])->all(),
        ]];
    }

    public function defaults(): array
    {
        return ['from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->format('Y-m-d')];
    }

    public function columns(): array
    {
        return [
            'effective_date' => Column::date($this->label('effective_date')),
            'employee_number' => Column::code($this->label('employee_number')),
            'employee' => Column::text($this->label('employee')),
            'change_type' => Column::text($this->label('change_type')),
            'branch' => Column::text($this->label('branch')),
            'department' => Column::text($this->label('department')),
            'position' => Column::text($this->label('position')),
            'job_title' => Column::text($this->label('job_title')),
            'manager' => Column::text($this->label('manager')),
            'status' => Column::text($this->label('status')),
            'reason' => Column::text($this->label('reason')),
        ];
    }

    public function sortable(): array
    {
        return ['effective_date' => 'employee_assignments.effective_date', 'change_type' => 'employee_assignments.change_type'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = EmployeeAssignment::query()
            ->with(['employee', 'branch', 'department', 'position', 'jobTitle', 'manager'])
            ->whereNotNull('employee_assignments.applied_at')
            ->whereNull('employee_assignments.cancelled_at')
            ->whereBetween('employee_assignments.effective_date', [$filters['from'], $filters['to']])
            ->when($filters['change_type'] ?? null, fn (Builder $query, string $type) => $query->where('employee_assignments.change_type', $type))
            ->orderBy('employee_assignments.effective_date')->orderBy('employee_assignments.id');

        return ReportFilters::applyEmployeeFilters($query, 'employee_assignments.employee_id', $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var EmployeeAssignment $record */
        return [
            'effective_date' => $record->effective_date,
            'employee_number' => $record->employee?->employee_number,
            'employee' => $record->employee?->name,
            'change_type' => $record->change_type?->label(),
            'branch' => $record->branch?->name,
            'department' => $record->department?->name,
            'position' => $record->position?->name,
            'job_title' => $record->jobTitle?->name,
            'manager' => $record->manager?->name,
            'status' => $record->status?->label(),
            'reason' => $record->reason,
        ];
    }
}
