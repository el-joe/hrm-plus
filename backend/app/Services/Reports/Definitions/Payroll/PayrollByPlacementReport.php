<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Models\User;
use App\Services\Reports\Column;
use App\Support\Charts\ChartData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Live pay grouped by the placement stored on the payroll item (branch or department).
 */
abstract class PayrollByPlacementReport extends PayrollReport
{
    /**
     * @return array{0: string, 1: string} item column, joined table
     */
    abstract protected function placement(): array;

    public function columns(): array
    {
        return [
            'code' => Column::code($this->label('code')),
            'name' => Column::text($this->label('name')),
            'employees' => Column::number($this->label('employees')),
            'gross' => Column::money($this->label('gross')),
            'deductions' => Column::money($this->label('deductions')),
            'net' => Column::money($this->label('net')),
            'employer' => Column::money($this->label('employer_cost')),
        ];
    }

    public function sortable(): array
    {
        return ['code' => 'code', 'gross' => 'gross', 'net' => 'net', 'employees' => 'employees'];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Builder
    {
        [$column, $table] = $this->placement();

        return $this->items($filters)
            ->leftJoin($table, "{$table}.id", '=', "payroll_items.{$column}")
            ->select("{$table}.code", "{$table}.name_ar", "{$table}.name_en")
            ->selectRaw('count(distinct payroll_items.employee_id) as employees, sum(payroll_items.gross_pay) as gross, sum(payroll_items.total_deductions) as deductions')
            ->selectRaw('sum(payroll_items.net_pay) as net, sum(payroll_items.employer_contributions) as employer')
            ->groupBy("payroll_items.{$column}", "{$table}.code", "{$table}.name_ar", "{$table}.name_en")
            ->orderBy("{$table}.code");
    }

    public function row(mixed $record, array $filters): array
    {
        return [
            'code' => $record->code ?? '—',
            'name' => $record->code === null ? __('reports.ui.not_set') : $this->periodName($record->name_ar, $record->name_en),
            'employees' => (int) $record->employees,
            'gross' => (string) $record->gross,
            'deductions' => (string) $record->deductions,
            'net' => (string) $record->net,
            'employer' => (string) $record->employer,
        ];
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $rows = $rows->take(20);

        return ChartData::make('bar', $rows->pluck('code')->all(), [
            ['label' => $this->label('net'), 'data' => $rows->pluck('net')->all(), 'color' => 'chart-1'],
            ['label' => $this->label('deductions'), 'data' => $rows->pluck('deductions')->all(), 'color' => 'chart-5'],
        ], ['stacked' => true]);
    }
}
