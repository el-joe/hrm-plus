<?php

namespace Database\Factories;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open monthly period (code unique per factory call, dates of the current month).
 *
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 9_999_999);

        return [
            'code' => 'PP-'.$number,
            'name_ar' => 'فترة '.$number,
            'name_en' => 'Period '.$number,
            'type' => PayrollPeriodType::Monthly,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'pay_date' => now()->endOfMonth()->toDateString(),
            'status' => PayrollPeriodStatus::Open,
        ];
    }
}
