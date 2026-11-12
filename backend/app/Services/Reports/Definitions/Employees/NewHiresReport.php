<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employees hired (or rehired) in the period: employees.hire_date inside the range.
 */
class NewHiresReport extends Report
{
    public function key(): string
    {
        return 'new_hires';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'position' => Column::text($this->label('position')),
            'employment_type' => Column::text($this->label('employment_type')),
            'manager' => Column::text($this->label('manager')),
            'hire_date' => Column::date($this->label('hire_date')),
            'probation_end' => Column::date($this->label('probation_end')),
            'status' => Column::text($this->label('status')),
        ];
    }

    public function sortable(): array
    {
        return ['employee_number' => 'employees.employee_number', 'employee' => Employee::localizedColumn('name'), 'hire_date' => 'employees.hire_date'];
    }

    public function query(array $filters, User $user): Builder
    {
        return ReportFilters::constrainEmployees(Employee::query(), $filters)
            ->with(['branch', 'department', 'position', 'employmentType', 'manager'])
            ->whereBetween('employees.hire_date', [$filters['from'], $filters['to']])
            ->orderBy('employees.hire_date')->orderBy('employees.employee_number');
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var Employee $record */
        return [
            ...$this->employeeCells($record),
            'position' => $record->position?->name,
            'employment_type' => $record->employmentType?->name,
            'manager' => $record->manager?->name,
            'hire_date' => $record->hire_date,
            'probation_end' => $record->probation_end_date,
            'status' => $record->status?->label(),
        ];
    }
}
