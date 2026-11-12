<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Enums\EmployeeStatus;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Employee directory: placement, contact and status of the employees in scope (still employed unless an
 * employee status is chosen — terminated employees appear with status = terminated). Basic salary is a
 * financial column (employee_financials.view, logged); active warnings a disciplinary column
 * (disciplinary.view, logged).
 */
class EmployeeDirectoryReport extends Report
{
    public function key(): string
    {
        return 'employee_directory';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['date_range']));
    }

    public function defaults(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'team' => Column::text($this->label('team')),
            'job_title' => Column::text($this->label('job_title')),
            'employment_type' => Column::text($this->label('employment_type')),
            'manager' => Column::text($this->label('manager')),
            'status' => Column::text($this->label('status')),
            'hire_date' => Column::date($this->label('hire_date')),
            'email' => Column::code($this->label('email')),
            'phone' => Column::code($this->label('phone')),
            'basic_salary' => Column::money($this->label('basic_salary')),
            'active_warnings' => Column::number($this->label('active_warnings'))->guardedBy('disciplinary'),
        ];
    }

    public function sortable(): array
    {
        return [
            'employee_number' => 'employees.employee_number',
            'employee' => Employee::localizedColumn('name'),
            'hire_date' => 'employees.hire_date',
            'status' => 'employees.status',
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = ReportFilters::constrainEmployees(Employee::query(), $filters)
            ->with(['branch', 'department', 'team', 'jobTitle', 'employmentType', 'manager'])
            ->when(! isset($filters['employee_status']), fn (Builder $query) => $query->where('employees.status', '!=', EmployeeStatus::Terminated->value))
            ->select('employees.*')
            ->orderBy('employees.employee_number');

        if ($user->can('disciplinary.view')) {
            $query->addSelect(['active_warnings' => DisciplinaryAction::query()->selectRaw('count(*)')
                ->whereColumn('disciplinary_actions.employee_id', 'employees.id')->activeWarnings()]);
        }

        return $query;
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var Employee $record */
        return [
            ...$this->employeeCells($record),
            'team' => $record->team?->name,
            'job_title' => $record->jobTitle?->name,
            'employment_type' => $record->employmentType?->name,
            'manager' => $record->manager?->name,
            'status' => $record->status?->label(),
            'hire_date' => $record->hire_date,
            'email' => $record->email,
            'phone' => $record->phone,
            'basic_salary' => $record->getRawOriginal('basic_salary'),
            'active_warnings' => $record->getAttribute('active_warnings'),
        ];
    }
}
