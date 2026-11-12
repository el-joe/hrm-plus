<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base of the payroll reports. They read ONLY frozen results (backend/docs/PAYROLL_ENGINE.md §10):
 * payroll_items of runs with status "finalised", periods whose end date is inside the range;
 * grouping by the placement stored on the item (period end). Every amount is a DECIMAL summed in
 * SQL or with bcmath. Payroll reports need employee_financials.view on top of reports.view
 * (a payroll report without amounts would be empty) and log the financial access.
 */
abstract class PayrollReport extends Report
{
    public function area(): string
    {
        return 'payroll';
    }

    public function requires(): array
    {
        return ['employee_financials.view'];
    }

    public function defaults(): array
    {
        return ['from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->endOfMonth()->format('Y-m-d')];
    }

    public function extraFilters(): array
    {
        return ['payroll_period_id' => [
            'label' => __('reports.filters.payroll_period'),
            'options' => PayrollPeriod::query()->orderByDesc('start_date')->limit(36)->get()->mapWithKeys(fn (PayrollPeriod $period): array => [$period->id => $period->code.' · '.$period->name])->all(),
        ]];
    }

    /**
     * Items of finalised runs in the period, joined to their run and period. Live pay (default):
     * regular pay slot of finalised runs; otherwise every item of finalised and reversed runs
     * (history: a reversal run's negative mirror sits next to the reversed original).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<PayrollItem>
     */
    protected function items(array $filters, bool $liveOnly = true): Builder
    {
        $query = PayrollItem::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_items.payroll_period_id')
            ->whereIn('payroll_runs.status', $liveOnly ? [PayrollRunStatus::Finalised->value] : [PayrollRunStatus::Finalised->value, PayrollRunStatus::Reversed->value])
            ->whereBetween('payroll_periods.end_date', [$filters['from'], $filters['to']])
            ->when($liveOnly, fn (Builder $query) => $query->where('payroll_items.pay_slot', 'regular'))
            ->when($filters['payroll_period_id'] ?? null, fn (Builder $query, $id) => $query->where('payroll_items.payroll_period_id', $id));

        return ReportFilters::applyEmployeeFilters($query, 'payroll_items.employee_id', $filters, [
            'branch' => 'payroll_items.branch_id', 'department' => 'payroll_items.department_id',
            'team' => 'payroll_items.team_id', 'position' => 'payroll_items.position_id',
        ]);
    }

    protected function periodName(?string $nameAr, ?string $nameEn): string
    {
        return (string) (app()->getLocale() === 'en' ? ($nameEn ?: $nameAr) : ($nameAr ?: $nameEn));
    }
}
