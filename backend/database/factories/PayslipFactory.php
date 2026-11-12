<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PayslipStatus;
use App\Models\PayrollItem;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An issued, unpaid English payslip of a payroll item (amounts copied from the item).
 *
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = fn (array $attributes): PayrollItem => PayrollItem::query()->withoutGlobalScopes()->findOrFail($attributes['payroll_item_id']);

        return [
            'number' => 'PS-'.str_pad((string) fake()->unique()->numberBetween(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'payroll_item_id' => PayrollItem::factory(),
            'payroll_run_id' => fn (array $attributes) => $item($attributes)->payroll_run_id,
            'payroll_period_id' => fn (array $attributes) => $item($attributes)->payroll_period_id,
            'employee_id' => fn (array $attributes) => $item($attributes)->employee_id,
            'branch_id' => fn (array $attributes) => $item($attributes)->branch_id,
            'department_id' => fn (array $attributes) => $item($attributes)->department_id,
            'status' => PayslipStatus::Issued,
            'payment_status' => PaymentStatus::Unpaid,
            'currency' => fn (array $attributes) => $item($attributes)->currency,
            'gross_pay' => fn (array $attributes) => $item($attributes)->gross_pay,
            'total_deductions' => fn (array $attributes) => $item($attributes)->total_deductions,
            'net_pay' => fn (array $attributes) => $item($attributes)->net_pay,
            'amount_paid' => '0.00',
            'locale' => 'en',
            'issued_at' => now(),
        ];
    }
}
