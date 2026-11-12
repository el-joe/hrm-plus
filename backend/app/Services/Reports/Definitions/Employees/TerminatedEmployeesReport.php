<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employees whose employment ended in the period (status terminated, termination_date inside the range).
 */
class TerminatedEmployeesReport extends Report
{
    public function key(): string
    {
        return 'terminated_employees';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['employee_status']));
    }

    public function defaults(): array
    {
        $defaults = parent::defaults();
        $defaults['from'] = now()->startOfYear()->format('Y-m-d');

        return $defaults;
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'position' => Column::text($this->label('position')),
            'hire_date' => Column::date($this->label('hire_date')),
            'termination_date' => Column::date($this->label('termination_date')),
            'termination_type' => Column::text($this->label('termination_type')),
            'reason' => Column::text($this->label('reason')),
            'service_years' => Column::decimal($this->label('service_years'), 1, false),
            'eligible_for_rehire' => Column::text($this->label('eligible_for_rehire')),
        ];
    }

    public function sortable(): array
    {
        return ['employee_number' => 'employees.employee_number', 'termination_date' => 'employees.termination_date', 'hire_date' => 'employees.hire_date'];
    }

    public function query(array $filters, User $user): Builder
    {
        return ReportFilters::constrainEmployees(Employee::query(), $filters)
            ->with(['branch', 'department', 'position'])
            ->where('employees.status', EmployeeStatus::Terminated->value)
            ->whereBetween('employees.termination_date', [$filters['from'], $filters['to']])
            ->orderBy('employees.termination_date')->orderBy('employees.employee_number');
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var Employee $record */
        $start = $record->original_hire_date ?? $record->hire_date;

        return [
            ...$this->employeeCells($record),
            'position' => $record->position?->name,
            'hire_date' => $record->hire_date,
            'termination_date' => $record->termination_date,
            'termination_type' => $record->termination_type?->label(),
            'reason' => $record->termination_reason,
            'service_years' => $start && $record->termination_date ? number_format($start->diffInDays($record->termination_date) / 365.25, 1, '.', '') : null,
            'eligible_for_rehire' => $record->eligible_for_rehire === null ? null : __($record->eligible_for_rehire ? 'reports.ui.yes' : 'reports.ui.no'),
        ];
    }
}
