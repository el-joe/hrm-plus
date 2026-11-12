<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Models\User;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Payroll summary per period: employees paid, gross, deductions, net and employer cost (live pay).
 */
class PayrollSummaryReport extends PayrollReport
{
    public function key(): string
    {
        return 'payroll_summary';
    }

    public function columns(): array
    {
        return [
            'period' => Column::text($this->label('period')),
            'pay_date' => Column::date($this->label('pay_date')),
            'employees' => Column::number($this->label('employees'), false),
            'gross' => Column::money($this->label('gross')),
            'deductions' => Column::money($this->label('deductions')),
            'net' => Column::money($this->label('net')),
            'employer' => Column::money($this->label('employer_cost')),
        ];
    }

    public function sortable(): array
    {
        return ['pay_date' => 'payroll_periods.pay_date', 'gross' => 'gross', 'net' => 'net', 'employees' => 'employees'];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Builder
    {
        return $this->items($filters)
            ->select('payroll_periods.id as period_id', 'payroll_periods.name_ar', 'payroll_periods.name_en', 'payroll_periods.pay_date', 'payroll_periods.end_date')
            ->selectRaw('count(distinct payroll_items.employee_id) as employees, sum(payroll_items.gross_pay) as gross, sum(payroll_items.total_deductions) as deductions')
            ->selectRaw('sum(payroll_items.net_pay) as net, sum(payroll_items.employer_contributions) as employer')
            ->groupBy('payroll_periods.id', 'payroll_periods.name_ar', 'payroll_periods.name_en', 'payroll_periods.pay_date', 'payroll_periods.end_date')
            ->orderBy('payroll_periods.end_date');
    }

    public function row(mixed $record, array $filters): array
    {
        return [
            'period' => $this->periodName($record->name_ar, $record->name_en),
            'pay_date' => $record->pay_date,
            'employees' => (int) $record->employees,
            'gross' => (string) $record->gross,
            'deductions' => (string) $record->deductions,
            'net' => (string) $record->net,
            'employer' => (string) $record->employer,
        ];
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        return ChartData::make('line', $rows->pluck('period')->all(), [
            ['label' => $this->label('gross'), 'data' => $rows->pluck('gross')->all(), 'color' => 'chart-1'],
            ['label' => $this->label('net'), 'data' => $rows->pluck('net')->all(), 'color' => 'chart-2'],
            ['label' => $this->label('employer_cost'), 'data' => $rows->pluck('employer')->all(), 'color' => 'chart-4'],
        ], ['decimals' => 0]);
    }
}
