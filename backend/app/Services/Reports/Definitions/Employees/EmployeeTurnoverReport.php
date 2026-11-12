<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turnover per month of the period: headcount at the start and end of the month (hired on or
 * before the day and not terminated by then), hires and terminations in the month, and the
 * turnover rate = terminations ÷ average headcount × 100. One aggregate query for every month.
 */
class EmployeeTurnoverReport extends Report
{
    public function key(): string
    {
        return 'employee_turnover';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['employee_status', 'employee']));
    }

    public function defaults(): array
    {
        $today = CarbonImmutable::today();

        return ['from' => $today->subMonths(11)->startOfMonth()->format('Y-m-d'), 'to' => $today->format('Y-m-d')];
    }

    public function columns(): array
    {
        return [
            'month' => Column::code(__('reports.columns.month')),
            'opening' => Column::number(__('reports.columns.opening_headcount'), false),
            'hires' => Column::number(__('reports.columns.hires')),
            'terminations' => Column::number(__('reports.columns.terminations')),
            'closing' => Column::number(__('reports.columns.closing_headcount'), false),
            'turnover_rate' => Column::percent(__('reports.columns.turnover_rate'), 2),
        ];
    }

    public function hasChart(): bool
    {
        return true;
    }

    /**
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function months(string $from, string $to): array
    {
        $months = [];
        $cursor = CarbonImmutable::parse($from)->startOfMonth();
        $end = CarbonImmutable::parse($to);

        while ($cursor->lte($end) && count($months) < 36) {
            $months[] = [$cursor->max(CarbonImmutable::parse($from)), $cursor->endOfMonth()->min($end)->startOfDay()];
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    public function query(array $filters, User $user): Collection
    {
        $months = self::months($filters['from'], $filters['to']);
        $query = ReportFilters::constrainEmployees(Employee::query(), $filters, array_diff(array_keys(ReportFilters::PARAMS), ['employee_status', 'employee']));
        $headcount = fn (string $day): string => "sum(case when employees.hire_date <= '{$day}' and (employees.termination_date is null or employees.termination_date > '{$day}') then 1 else 0 end)";

        foreach ($months as $index => [$start, $end]) {
            $before = $start->subDay()->format('Y-m-d');
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
            $query->selectRaw($headcount($before)." as opening_{$index}")
                ->selectRaw($headcount($to)." as closing_{$index}")
                ->selectRaw("sum(case when employees.hire_date between '{$from}' and '{$to}' then 1 else 0 end) as hires_{$index}")
                ->selectRaw("sum(case when employees.termination_date between '{$from}' and '{$to}' then 1 else 0 end) as terminations_{$index}");
        }

        $totals = $months === [] ? null : $query->toBase()->first();

        return collect($months)->map(function (array $month, int $index) use ($totals): array {
            $opening = (int) ($totals?->{"opening_{$index}"} ?? 0);
            $closing = (int) ($totals?->{"closing_{$index}"} ?? 0);
            $terminations = (int) ($totals?->{"terminations_{$index}"} ?? 0);
            $average = ($opening + $closing) / 2;

            return [
                'month' => $month[0]->format('Y-m'),
                'opening' => $opening,
                'hires' => (int) ($totals?->{"hires_{$index}"} ?? 0),
                'terminations' => $terminations,
                'closing' => $closing,
                'turnover_rate' => $average > 0 ? round($terminations * 100 / $average, 2) : 0,
            ];
        });
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        return ChartData::make('bar', $rows->pluck('month')->all(), [
            ['label' => __('reports.columns.hires'), 'data' => $rows->pluck('hires')->all(), 'color' => 'chart-1'],
            ['label' => __('reports.columns.terminations'), 'data' => $rows->pluck('terminations')->all(), 'color' => 'chart-5'],
        ]);
    }
}
