<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payslip;
use App\Models\PayslipPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bank transfer paying a payslip in full.
 *
 * @extends Factory<PayslipPayment>
 */
class PayslipPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payslip_id' => Payslip::factory(),
            'amount' => fn (array $attributes) => Payslip::query()->withoutGlobalScopes()->findOrFail($attributes['payslip_id'])->net_pay,
            'paid_on' => now()->toDateString(),
            'method' => PaymentMethod::BankTransfer,
            'reference' => 'TRX-'.fake()->numerify('########'),
        ];
    }
}
