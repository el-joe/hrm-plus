<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\PayComponentKind;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deductions withheld (every deduction line: attendance, leave, loans and advances, tax,
 * insurance, penalties, other).
 */
class DeductionsReport extends PayrollLinesReport
{
    public function key(): string
    {
        return 'deductions';
    }

    protected function constrainLines(Builder $query): void
    {
        $query->where('payroll_item_lines.kind', PayComponentKind::Deduction->value);
    }

    protected function components(): Builder
    {
        return SalaryComponent::query()->where('kind', PayComponentKind::Deduction->value);
    }
}
