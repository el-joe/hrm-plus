<?php

namespace Database\Factories;

use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft regular run for the whole company, without items.
 *
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'PR-'.str_pad((string) fake()->unique()->numberBetween(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'payroll_period_id' => PayrollPeriod::factory(),
            'type' => PayrollRunType::Regular,
            'name' => 'Regular payroll',
            'status' => PayrollRunStatus::Draft,
            'scope' => ['all' => true],
            'currency' => 'USD',
        ];
    }
}
