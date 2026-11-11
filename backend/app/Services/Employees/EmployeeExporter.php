<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Exports the (filtered, data-scoped) employee list to XLSX or CSV. Columns
 * follow the exporter's permissions: identity numbers need
 * employee_sensitive.view, salary and bank details employee_financials.view.
 * Every export is written to the sensitive-data access log with the row
 * count, the protected columns included and the filters.
 */
class EmployeeExporter
{
    public function __construct(
        private readonly Spreadsheet $spreadsheet,
        private readonly SensitiveDataAccessLog $accessLog,
    ) {}

    /**
     * @return array<string, string> column key => header
     */
    public function columns(User $user): array
    {
        $columns = [];

        foreach (['employee_number', 'name_ar', 'name_en', 'status', 'branch', 'department', 'team', 'position', 'job_title', 'job_grade',
            'employment_type', 'cost_center', 'manager_number', 'manager_name', 'hire_date', 'original_hire_date', 'probation_end_date',
            'termination_date', 'email', 'phone', 'gender', 'marital_status', 'nationality', 'date_of_birth', 'city'] as $column) {
            $columns[$column] = __('employees.export.columns.'.$column);
        }

        if ($user->can('employee_sensitive.view')) {
            $columns['national_id'] = __('employees.export.columns.national_id');
            $columns['passport_number'] = __('employees.export.columns.passport_number');
        }

        if ($user->can('employee_financials.view')) {
            $columns['basic_salary'] = __('employees.export.columns.basic_salary');
            $columns['iban'] = __('employees.export.columns.iban');
        }

        return $columns;
    }

    /**
     * Write the file and return its temporary path.
     *
     * @param  Builder<Employee>  $query
     * @param  array<string, mixed>  $filters
     */
    public function export(Builder $query, User $user, string $format, array $filters = []): string
    {
        $columns = $this->columns($user);
        $path = storage_path('app/private/'.Str::uuid()->toString().'.'.$format);
        $count = 0;

        $rows = (function () use ($query, $columns, &$count) {
            $query->with(['branch', 'department', 'team', 'position', 'jobTitle', 'jobGrade', 'employmentType', 'costCenter', 'manager',
                'bankAccounts' => fn ($accounts) => $accounts->orderByDesc('is_primary')->orderBy('id')]);

            foreach ($query->reorder()->orderBy('employees.employee_number')->lazy(500) as $employee) {
                $count++;

                yield array_map(fn (string $column) => $this->value($employee, $column), array_keys($columns));
            }
        })();

        $this->spreadsheet->write($path, $format, array_values($columns), $rows);

        $this->accessLog->record(SensitiveDataAccessLog::EXPORT, null, array_values(array_intersect(array_keys($columns), ['national_id', 'passport_number', 'basic_salary', 'iban'])), [
            'rows' => $count,
            'format' => $format,
            'filters' => array_filter($filters, fn ($value) => filled($value)),
        ], $user);

        return $path;
    }

    private function value(Employee $employee, string $column): mixed
    {
        return match ($column) {
            'status' => $employee->status->label(),
            'branch', 'department', 'team', 'position', 'job_title', 'job_grade', 'employment_type', 'cost_center' => $employee->{Str::camel($column)}?->name,
            'manager_number' => $employee->manager?->employee_number,
            'manager_name' => $employee->manager?->name,
            'gender', 'marital_status' => $employee->{$column}?->label(),
            'basic_salary' => $employee->basic_salary,
            'iban' => $employee->bankAccounts->first()?->iban,
            default => $employee->{$column},
        };
    }
}
