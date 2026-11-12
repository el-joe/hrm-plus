<?php

namespace App\Services\Reports;

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use App\Services\Authorization\DataScopeManager;
use App\Support\Formatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The shared filters of the reports centre (spec module 16: date range, branch, department,
 * team, employee, employee status, job title, manager, employment type — plus position and
 * employee group): validation rules, option lists for the filter bar, and one place that turns
 * the employee filters into SQL.
 *
 * applyEmployeeFilters() limits any query by its employee column through a sub-query on the
 * data-scoped Employee model, so tables that are not scoped themselves (raw events, payroll
 * lines, stage changes…) still follow the admin's data scope. Snapshot columns (the branch /
 * department stored on a payroll item or a review) can be named so the placement filters use them.
 */
class ReportFilters
{
    /**
     * Every shared filter, in filter-bar order.
     */
    public const SHARED = ['date_range', 'branch', 'department', 'team', 'position', 'job_title', 'employee_group', 'employment_type', 'employee_status', 'manager', 'employee'];

    public const EMPLOYEE_FILTERS = ['date_range', 'branch', 'department', 'team', 'position', 'job_title', 'employee_group', 'employment_type', 'employee_status', 'manager', 'employee'];

    /**
     * Request parameter of each shared filter (date_range uses from / to).
     */
    public const PARAMS = [
        'branch' => 'branch_id', 'department' => 'department_id', 'team' => 'team_id', 'position' => 'position_id',
        'job_title' => 'job_title_id', 'employee_group' => 'group_id', 'employment_type' => 'employment_type_id',
        'employee_status' => 'employee_status', 'manager' => 'manager_id', 'employee' => 'employee',
    ];

    public function __construct(private readonly DataScopeManager $scopes) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(Report $report): array
    {
        $rules = [];
        $filters = $report->filters();

        if (in_array('date_range', $filters, true)) {
            $rules['from'] = ['nullable', 'date_format:Y-m-d'];
            $rules['to'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'];
        }

        foreach (self::PARAMS as $filter => $param) {
            if (! in_array($filter, $filters, true)) {
                continue;
            }

            $rules[$param] = match ($filter) {
                'employee_status' => ['nullable', Rule::enum(EmployeeStatus::class)],
                'employee' => ['nullable', 'string', 'max:100'],
                default => ['nullable', 'integer', 'min:1'],
            };
        }

        foreach ($report->extraFilters() as $key => $filter) {
            $rules[$key] = $filter['rules'] ?? ['nullable', Rule::in(array_map('strval', array_keys($filter['options'])))];
        }

        $rules['sort'] = ['nullable', 'string', 'max:40'];
        $rules['direction'] = ['nullable', Rule::in(['asc', 'desc'])];

        return $rules;
    }

    /**
     * Validated input + the report's defaults; enforces the longest range.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function resolve(Report $report, array $validated): array
    {
        $filters = array_filter($validated, fn ($value) => $value !== null && $value !== '');
        unset($filters['sort'], $filters['direction']);

        foreach ($report->defaults() as $key => $value) {
            $filters[$key] ??= $value;
        }

        foreach (['branch_id', 'department_id', 'team_id', 'position_id', 'job_title_id', 'group_id', 'employment_type_id', 'manager_id'] as $key) {
            if (isset($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }

        if (isset($filters['from'], $filters['to'])) {
            if ($filters['to'] < $filters['from']) {
                $filters['to'] = $filters['from'];
            }

            if (CarbonImmutable::parse($filters['from'])->diffInDays(CarbonImmutable::parse($filters['to'])) > $report->maxRangeDays()) {
                throw ValidationException::withMessages(['to' => __('reports.messages.range_too_long', ['days' => $report->maxRangeDays()])]);
            }
        }

        ksort($filters);

        return $filters;
    }

    /**
     * Limit a query to the employees matching the filters (and the admin's data scope).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $snapshot  filter (branch, department, team, position) => column on the query
     * @return Builder<TModel>
     */
    public static function applyEmployeeFilters(Builder $query, string $employeeColumn, array $filters, array $snapshot = [], bool $alwaysScope = true): Builder
    {
        $manager = app(DataScopeManager::class);

        foreach ($snapshot as $filter => $column) {
            $value = $filters[self::PARAMS[$filter]] ?? null;

            if ($value === null) {
                continue;
            }

            if ($filter === 'department') {
                $query->whereIn($column, $manager->withSubDepartments([(int) $value]));
            } else {
                $query->where($column, $value);
            }
        }

        $employeeFilters = array_diff(array_keys(self::PARAMS), array_keys($snapshot));
        $hasFilter = collect($employeeFilters)->contains(fn (string $filter): bool => ($filters[self::PARAMS[$filter]] ?? null) !== null);
        $restricted = $manager->activeAdmin()?->hasDataRestrictions() ?? false;

        if (! $hasFilter && ! ($alwaysScope && $restricted)) {
            return $query;
        }

        $employees = Employee::query()->select('employees.id');
        self::constrainEmployees($employees, $filters, array_values($employeeFilters));

        return $query->whereIn($employeeColumn, $employees);
    }

    /**
     * The employee filters on an Employee query.
     *
     * @param  Builder<Employee>  $query
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $only
     * @return Builder<Employee>
     */
    public static function constrainEmployees(Builder $query, array $filters, ?array $only = null): Builder
    {
        $only ??= array_keys(self::PARAMS);
        $value = fn (string $filter) => in_array($filter, $only, true) ? ($filters[self::PARAMS[$filter]] ?? null) : null;

        return $query
            ->when($value('branch'), fn (Builder $query, $id) => $query->where('employees.branch_id', $id))
            ->when($value('department'), fn (Builder $query, $id) => $query->whereIn('employees.department_id', app(DataScopeManager::class)->withSubDepartments([(int) $id])))
            ->when($value('team'), fn (Builder $query, $id) => $query->where('employees.team_id', $id))
            ->when($value('position'), fn (Builder $query, $id) => $query->where('employees.position_id', $id))
            ->when($value('job_title'), fn (Builder $query, $id) => $query->where('employees.job_title_id', $id))
            ->when($value('employment_type'), fn (Builder $query, $id) => $query->where('employees.employment_type_id', $id))
            ->when($value('employee_status'), fn (Builder $query, $status) => $query->where('employees.status', $status))
            ->when($value('manager'), fn (Builder $query, $id) => $query->where('employees.manager_id', $id))
            ->when($value('employee_group'), fn (Builder $query, $id) => $query->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('employee_group_members')->whereColumn('employee_group_members.employee_id', 'employees.id')->where('employee_group_members.employee_group_id', $id)))
            ->when($value('employee'), fn (Builder $query, string $term) => $query->search($term));
    }

    /**
     * Options of the shared selects this report uses (records the admin may see).
     *
     * @return array<string, array<int|string, string>>
     */
    public function options(Report $report): array
    {
        $filters = $report->filters();
        $list = fn (string $model) => $model::query()->orderBy('code')->get()->mapWithKeys(fn ($record): array => [$record->id => $record->code.' · '.$record->name])->all();
        $options = [];

        foreach ([
            'branch' => Branch::class, 'department' => Department::class, 'team' => Team::class, 'position' => Position::class,
            'job_title' => JobTitle::class, 'employee_group' => EmployeeGroup::class, 'employment_type' => EmploymentType::class,
        ] as $filter => $model) {
            if (in_array($filter, $filters, true)) {
                $options[$filter] = $list($model);
            }
        }

        if (in_array('employee_status', $filters, true)) {
            $options['employee_status'] = collect(EmployeeStatus::cases())->mapWithKeys(fn (EmployeeStatus $status): array => [$status->value => $status->label()])->all();
        }

        if (in_array('manager', $filters, true)) {
            $options['manager'] = Employee::query()
                ->whereIn('employees.id', Employee::query()->withoutGlobalScopes()->select('manager_id')->whereNotNull('manager_id'))
                ->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en'])
                ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->employee_number.' · '.$employee->name])->all();
        }

        return $options;
    }

    /**
     * Human-readable summary of the active filters (PDF header, export notification).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string> label => value
     */
    public function describe(Report $report, array $filters): array
    {
        $described = [];

        if (isset($filters['from'], $filters['to'])) {
            $described[__('reports.filters.period')] = Formatter::date($filters['from']).' – '.Formatter::date($filters['to']);
        }

        $options = null;

        foreach (self::PARAMS as $filter => $param) {
            if (! isset($filters[$param]) || ! in_array($filter, $report->filters(), true)) {
                continue;
            }

            if ($filter === 'employee') {
                $described[__('reports.filters.employee')] = (string) $filters[$param];

                continue;
            }

            $options ??= $this->options($report);
            $described[__('reports.filters.'.$filter)] = $options[$filter][$filters[$param]] ?? '#'.$filters[$param];
        }

        foreach ($report->extraFilters() as $key => $filter) {
            if (isset($filters[$key])) {
                $described[$filter['label']] = $filter['options'][$filters[$key]] ?? (string) $filters[$key];
            }
        }

        return $described;
    }
}
