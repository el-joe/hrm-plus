<?php

namespace Database\Factories;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineSource;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 500 allowance line on a payroll item (amounts are not recalculated).
 *
 * @extends Factory<PayrollItemLine>
 */
class PayrollItemLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_item_id' => PayrollItem::factory(),
            'payroll_run_id' => fn (array $attributes) => PayrollItem::query()->withoutGlobalScopes()->findOrFail($attributes['payroll_item_id'])->payroll_run_id,
            'employee_id' => fn (array $attributes) => PayrollItem::query()->withoutGlobalScopes()->findOrFail($attributes['payroll_item_id'])->employee_id,
            'code' => 'HOUSING',
            'name_ar' => 'بدل سكن',
            'name_en' => 'Housing',
            'kind' => PayComponentKind::Earning,
            'category' => PayComponentCategory::Allowance,
            'source' => PayrollLineSource::Structure,
            'amount' => '500.00',
            'is_taxable' => true,
            'is_insurable' => false,
            'show_on_payslip' => true,
        ];
    }
}
