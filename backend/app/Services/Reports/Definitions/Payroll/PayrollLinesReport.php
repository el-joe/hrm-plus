<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Models\PayrollItemLine;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Payslip lines of live pay (payroll_item_lines) filtered by kind / category: one row per employee,
 * period and component, with quantity, rate and the signed amount. Lines are not data-scoped
 * themselves: they are limited to the (scoped) payroll items.
 */
abstract class PayrollLinesReport extends PayrollReport
{
    /**
     * @param  Builder<PayrollItemLine>  $query
     */
    abstract protected function constrainLines(Builder $query): void;

    /**
     * Components offered in the component filter.
     *
     * @return Builder<SalaryComponent>
     */
    abstract protected function components(): Builder;

    public function extraFilters(): array
    {
        return [...parent::extraFilters(), 'component' => [
            'label' => __('reports.filters.component'),
            'options' => $this->components()->orderBy('code')->get()->mapWithKeys(fn (SalaryComponent $component): array => [$component->code => $component->code.' · '.$component->name])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'period' => Column::text($this->label('period')),
            'component' => Column::text($this->label('component')),
            'category' => Column::text($this->label('category')),
            'quantity' => Column::decimal($this->label('quantity'), 2, false),
            'rate' => Column::money($this->label('rate'), false),
            'amount' => Column::money($this->label('amount')),
        ];
    }

    public function sortable(): array
    {
        return ['employee_number' => 'employees.employee_number', 'period' => 'payroll_periods.end_date', 'amount' => 'payroll_item_lines.amount', 'component' => 'payroll_item_lines.code'];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Builder
    {
        $items = $this->items($filters)->select('payroll_items.id');
        $query = PayrollItemLine::query()
            ->join('payroll_items', 'payroll_items.id', '=', 'payroll_item_lines.payroll_item_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_items.payroll_period_id')
            ->join('employees', 'employees.id', '=', 'payroll_item_lines.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'payroll_items.branch_id')
            ->leftJoin('departments', 'departments.id', '=', 'payroll_items.department_id')
            ->whereIn('payroll_item_lines.payroll_item_id', $items)
            ->when($filters['component'] ?? null, fn (Builder $query, string $code) => $query->where('payroll_item_lines.code', $code))
            ->select('payroll_item_lines.*', 'employees.employee_number', 'employees.name_ar as employee_ar', 'employees.name_en as employee_en',
                'branches.name_ar as branch_ar', 'branches.name_en as branch_en', 'departments.name_ar as department_ar', 'departments.name_en as department_en',
                'payroll_periods.name_ar as period_ar', 'payroll_periods.name_en as period_en')
            ->orderBy('payroll_periods.end_date')->orderBy('employees.employee_number')->orderBy('payroll_item_lines.display_order')->orderBy('payroll_item_lines.id');
        $this->constrainLines($query);

        return $query;
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PayrollItemLine $record */
        $local = fn (string $prefix): ?string => $this->periodName($record->getAttribute($prefix.'_ar'), $record->getAttribute($prefix.'_en')) ?: null;

        return [
            'employee_number' => $record->getAttribute('employee_number'),
            'employee' => $local('employee'),
            'branch' => $local('branch'),
            'department' => $local('department'),
            'period' => $local('period'),
            'component' => $record->code.' · '.$record->name(app()->getLocale()),
            'category' => $record->category?->label(),
            'quantity' => $record->quantity === null ? null : (string) $record->quantity,
            'rate' => $record->rate === null ? null : (string) $record->rate,
            'amount' => (string) $record->getRawOriginal('amount'),
        ];
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $byComponent = $rows->groupBy('component')
            ->map(fn (Collection $group): string => $group->reduce(fn (string $sum, array $row): string => bcadd($sum, (string) $row['amount'], 2), '0'))
            ->sortDesc()->take(10);

        return ChartData::series('bar', $this->label('amount'), $byComponent->all(), ['horizontal' => true]);
    }
}
