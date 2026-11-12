<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Allowances paid (earning lines of category allowance, bonus or commission).
 */
class AllowancesReport extends PayrollLinesReport
{
    private const CATEGORIES = [PayComponentCategory::Allowance, PayComponentCategory::Bonus, PayComponentCategory::Commission];

    public function key(): string
    {
        return 'allowances';
    }

    protected function constrainLines(Builder $query): void
    {
        $query->where('payroll_item_lines.kind', PayComponentKind::Earning->value)
            ->whereIn('payroll_item_lines.category', array_map(fn (PayComponentCategory $category): string => $category->value, self::CATEGORIES));
    }

    protected function components(): Builder
    {
        return SalaryComponent::query()->where('kind', PayComponentKind::Earning->value)->whereIn('category', array_map(fn (PayComponentCategory $category): string => $category->value, self::CATEGORIES));
    }
}
