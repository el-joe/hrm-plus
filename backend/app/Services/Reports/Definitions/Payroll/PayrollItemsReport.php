<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One row per employee and payroll item (period). Employees are loaded without the data scope
 * because the item's own (period-end) placement is what the scope applies to.
 */
abstract class PayrollItemsReport extends PayrollReport
{
    protected function liveOnly(): bool
    {
        return true;
    }

    public function sortable(): array
    {
        return ['employee_number' => 'employees.employee_number', 'period' => 'payroll_periods.end_date', 'gross' => 'payroll_items.gross_pay', 'net' => 'payroll_items.net_pay'];
    }

    public function query(array $filters, User $user): Builder
    {
        return $this->items($filters, $this->liveOnly())
            ->join('employees', 'employees.id', '=', 'payroll_items.employee_id')
            ->select('payroll_items.*', 'payroll_periods.name_ar as period_ar', 'payroll_periods.name_en as period_en', 'payroll_periods.end_date as period_end',
                'payroll_runs.reference as run_reference', 'payroll_runs.type as run_type')
            ->with(['employee' => fn ($query) => $query->withoutGlobalScopes(), 'branch' => fn ($query) => $query->withoutGlobalScopes(),
                'department' => fn ($query) => $query->withoutGlobalScopes(), 'payslip'])
            ->orderBy('payroll_periods.end_date')->orderBy('employees.employee_number')->orderBy('payroll_items.id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemCells(PayrollItem $item): array
    {
        /** @var Employee|null $employee */
        $employee = $item->employee;

        return [
            'employee_number' => $employee?->employee_number,
            'employee' => $employee?->name,
            'branch' => $item->branch?->name,
            'department' => $item->department?->name,
            'period' => $this->periodName($item->getAttribute('period_ar'), $item->getAttribute('period_en')),
        ];
    }
}
