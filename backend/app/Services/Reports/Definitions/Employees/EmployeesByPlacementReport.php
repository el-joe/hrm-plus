<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Head-count comparison by a placement (branch / department): active, inactive, employed, and
 * hires / terminations in the period. Subclasses name the placement.
 */
abstract class EmployeesByPlacementReport extends Report
{
    /**
     * employees column and the model holding the names.
     *
     * @return array{0: string, 1: class-string<Model>}
     */
    abstract protected function placement(): array;

    public function area(): string
    {
        return 'employees';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['employee_status', 'employee']));
    }

    public function columns(): array
    {
        return [
            'code' => Column::code(__('reports.columns.code')),
            'name' => Column::text(__('reports.columns.name')),
            'active' => Column::number(__('reports.columns.active')),
            'inactive' => Column::number(__('reports.columns.inactive')),
            'employed' => Column::number(__('reports.columns.employed')),
            'hires' => Column::number(__('reports.columns.hires_in_period')),
            'terminations' => Column::number(__('reports.columns.terminations_in_period')),
        ];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Collection
    {
        [$column, $model] = $this->placement();
        $rows = ReportFilters::constrainEmployees(Employee::query(), $filters, array_diff(array_keys(ReportFilters::PARAMS), ['employee_status', 'employee']))
            ->select("employees.{$column} as group_key")
            ->selectRaw("sum(case when employees.status = 'active' then 1 else 0 end) as active")
            ->selectRaw("sum(case when employees.status = 'inactive' then 1 else 0 end) as inactive")
            ->selectRaw('sum(case when employees.hire_date between ? and ? then 1 else 0 end) as hires', [$filters['from'], $filters['to']])
            ->selectRaw("sum(case when employees.status = 'terminated' and employees.termination_date between ? and ? then 1 else 0 end) as terminations", [$filters['from'], $filters['to']])
            ->groupBy("employees.{$column}")
            ->toBase()->get();

        $names = $model::query()->withoutGlobalScopes()->whereKey($rows->pluck('group_key')->filter()->all())->get()->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'code' => $names[$row->group_key]->code ?? '—',
            'name' => $names[$row->group_key]->name ?? __('reports.ui.not_set'),
            'active' => (int) $row->active,
            'inactive' => (int) $row->inactive,
            'employed' => (int) $row->active + (int) $row->inactive,
            'hires' => (int) $row->hires,
            'terminations' => (int) $row->terminations,
        ])->filter(fn (array $row): bool => $row['employed'] + $row['hires'] + $row['terminations'] > 0)->sortBy('code')->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $rows = $rows->take(20);

        return ChartData::make('bar', $rows->pluck('code')->all(), [
            ['label' => __('reports.columns.active'), 'data' => $rows->pluck('active')->all(), 'color' => 'chart-1'],
            ['label' => __('reports.columns.inactive'), 'data' => $rows->pluck('inactive')->all(), 'color' => 'chart-6'],
        ], ['stacked' => true]);
    }
}
