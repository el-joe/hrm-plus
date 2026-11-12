<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Illuminate\Support\Collection;

/**
 * Employee distribution by a chosen dimension (branch, department, team, position, job title,
 * job grade, employment type, gender, nationality, status). Employees still employed unless an
 * employee status is chosen.
 */
class EmployeeDistributionReport extends Report
{
    /**
     * Dimension => [employees column, model with names (null = enum / raw value)].
     */
    public const DIMENSIONS = [
        'branch' => ['branch_id', Branch::class], 'department' => ['department_id', Department::class], 'team' => ['team_id', Team::class],
        'position' => ['position_id', Position::class], 'job_title' => ['job_title_id', JobTitle::class], 'job_grade' => ['job_grade_id', JobGrade::class],
        'employment_type' => ['employment_type_id', EmploymentType::class], 'gender' => ['gender', null], 'nationality' => ['nationality', null], 'status' => ['status', null],
    ];

    public function key(): string
    {
        return 'employee_distribution';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['date_range', 'employee']));
    }

    public function extraFilters(): array
    {
        return ['group_by' => [
            'label' => __('reports.filters.group_by'),
            'options' => collect(array_keys(self::DIMENSIONS))->mapWithKeys(fn (string $key): array => [$key => __('reports.dimensions.'.$key)])->all(),
        ]];
    }

    public function defaults(): array
    {
        return ['group_by' => 'department'];
    }

    public function columns(): array
    {
        return [
            'group' => Column::text(__('reports.columns.group')),
            'active' => Column::number(__('reports.columns.active')),
            'inactive' => Column::number(__('reports.columns.inactive')),
            'terminated' => Column::number(__('reports.columns.terminated')),
            'total' => Column::number(__('reports.columns.employees')),
            'share' => Column::percent(__('reports.columns.share')),
        ];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Collection
    {
        [$column, $model] = self::DIMENSIONS[$filters['group_by']] ?? self::DIMENSIONS['department'];
        $query = ReportFilters::constrainEmployees(Employee::query(), $filters)
            ->when(! isset($filters['employee_status']), fn ($query) => $query->where('employees.status', '!=', EmployeeStatus::Terminated->value))
            ->select("employees.{$column} as group_key")
            ->selectRaw("sum(case when employees.status = 'active' then 1 else 0 end) as active")
            ->selectRaw("sum(case when employees.status = 'inactive' then 1 else 0 end) as inactive")
            ->selectRaw("sum(case when employees.status = 'terminated' then 1 else 0 end) as `terminated`")
            ->selectRaw('count(*) as total')
            ->groupBy("employees.{$column}");

        $rows = $query->toBase()->get();
        $grand = (int) $rows->sum('total');
        $names = $model === null ? [] : $model::query()->withoutGlobalScopes()->whereKey($rows->pluck('group_key')->filter()->all())->get()
            ->mapWithKeys(fn ($record): array => [$record->id => $record->code.' · '.$record->name])->all();

        return $rows->map(function (object $row) use ($names, $grand, $filters): array {
            $key = $row->group_key;
            $label = match (true) {
                $key === null || $key === '' => __('reports.ui.not_set'),
                ($filters['group_by'] ?? '') === 'gender' => Gender::tryFrom((string) $key)?->label() ?? (string) $key,
                ($filters['group_by'] ?? '') === 'status' => EmployeeStatus::tryFrom((string) $key)?->label() ?? (string) $key,
                ($filters['group_by'] ?? '') === 'nationality' => (string) $key,
                default => $names[$key] ?? '#'.$key,
            };

            return ['group' => $label, 'active' => (int) $row->active, 'inactive' => (int) $row->inactive, 'terminated' => (int) $row->terminated,
                'total' => (int) $row->total, 'share' => $grand > 0 ? round($row->total * 100 / $grand, 1) : 0];
        })->sortByDesc('total')->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $top = $rows->take(12);

        return ChartData::series($top->count() <= 6 ? 'doughnut' : 'bar', __('reports.columns.employees'), $top->pluck('total', 'group')->all(), ['horizontal' => $top->count() > 6]);
    }
}
