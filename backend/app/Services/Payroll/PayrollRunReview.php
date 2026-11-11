<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Figures of the run review screen, always from the stored items (inside the
 * viewer's data scope — PayrollItem is scoped): totals by component, by
 * branch and by department, and the variance of every employee's net pay
 * against their previous finalised pay (any earlier period).
 */
class PayrollRunReview
{
    /**
     * @return Builder<PayrollItem>
     */
    public function items(PayrollRun $run): Builder
    {
        return PayrollItem::query()->where('payroll_run_id', $run->id);
    }

    /**
     * @return array{employees: int, gross: string, deductions: string, net: string, employer: string, warnings: int, blocking: int}
     */
    public function totals(PayrollRun $run): array
    {
        $row = $this->items($run)->selectRaw('count(*) as employees, coalesce(sum(gross_pay),0) as gross, coalesce(sum(total_deductions),0) as deductions, coalesce(sum(net_pay),0) as net, coalesce(sum(employer_contributions),0) as employer, sum(case when has_blocking_warning = 1 then 1 else 0 end) as blocking, sum(case when json_length(coalesce(warnings, json_array())) > 0 then 1 else 0 end) as warnings')->first();

        return [
            'employees' => (int) $row->employees, 'gross' => bcadd((string) $row->gross, '0', 2), 'deductions' => bcadd((string) $row->deductions, '0', 2),
            'net' => bcadd((string) $row->net, '0', 2), 'employer' => bcadd((string) $row->employer, '0', 2), 'warnings' => (int) $row->warnings, 'blocking' => (int) $row->blocking,
        ];
    }

    /**
     * @return Collection<int, array{code: string, name: string, kind: string, employees: int, amount: string}>
     */
    public function byComponent(PayrollRun $run): Collection
    {
        return PayrollItemLine::query()->where('payroll_run_id', $run->id)
            ->whereIn('payroll_item_id', $this->items($run)->select('id'))
            ->selectRaw('code, max(name_ar) as name_ar, max(name_en) as name_en, kind, count(distinct payroll_item_id) as employees, sum(amount) as amount, min(display_order) as sort')
            ->groupBy('code', 'kind')->orderByRaw("field(kind, 'earning', 'deduction', 'employer_contribution')")->orderBy('sort')
            ->get()
            ->map(fn ($row): array => [
                'code' => $row->code, 'name' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en, 'kind' => (string) $row->getRawOriginal('kind'),
                'employees' => (int) $row->employees, 'amount' => bcadd((string) $row->amount, '0', 2),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, employees: int, gross: string, deductions: string, net: string}>
     */
    public function byPlacement(PayrollRun $run, string $column): Collection
    {
        $rows = $this->items($run)->selectRaw("{$column} as id, count(*) as employees, sum(gross_pay) as gross, sum(total_deductions) as deductions, sum(net_pay) as net")
            ->groupBy($column)->get();
        $names = ($column === 'branch_id' ? Branch::query() : Department::query())->withoutGlobalScopes()->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

        return $rows->map(fn ($row): array => [
            'id' => (int) $row->id, 'name' => $names->get($row->id)?->name ?? '#'.$row->id, 'employees' => (int) $row->employees,
            'gross' => bcadd((string) $row->gross, '0', 2), 'deductions' => bcadd((string) $row->deductions, '0', 2), 'net' => bcadd((string) $row->net, '0', 2),
        ])->sortByDesc('net')->values();
    }

    /**
     * Previous finalised net pay of each employee (latest earlier period), keyed by employee id.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array{net: string, period: string}>
     */
    public function previousNet(PayrollRun $run, array $employeeIds): array
    {
        $start = $run->period->start_date->toDateString();
        $rows = PayrollItem::query()->withoutGlobalScopes()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_items.payroll_period_id')
            ->whereIn('payroll_items.employee_id', $employeeIds)
            ->where('payroll_runs.status', PayrollRunStatus::Finalised)
            ->where('payroll_items.pay_slot', PayrollItem::PAY_SLOT)
            ->where('payroll_periods.end_date', '<', $start)
            ->orderBy('payroll_periods.end_date')
            ->get(['payroll_items.employee_id', 'payroll_items.net_pay', 'payroll_periods.code']);

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->employee_id] = ['net' => (string) $row->net_pay, 'period' => $row->code];
        }

        return $result;
    }

    /**
     * Variance in percent (one decimal) between the current and previous net, null without a previous one.
     */
    public function variance(string $current, ?string $previous): ?string
    {
        if ($previous === null || bccomp($previous, '0', 2) === 0) {
            return null;
        }

        return PayrollMath::round(PayrollMath::div(bcmul(bcsub($current, $previous, 2), '100', 6), $previous), 1);
    }
}
