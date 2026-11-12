<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Overtime paid (earning lines of category overtime: hours × rate from the approved overtime).
 */
class OvertimePaymentsReport extends PayrollLinesReport
{
    public function key(): string
    {
        return 'overtime_payments';
    }

    protected function constrainLines(Builder $query): void
    {
        $query->where('payroll_item_lines.kind', PayComponentKind::Earning->value)->where('payroll_item_lines.category', PayComponentCategory::Overtime->value);
    }

    protected function components(): Builder
    {
        return SalaryComponent::query()->where('category', PayComponentCategory::Overtime->value);
    }
}
