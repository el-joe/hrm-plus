<?php

namespace Database\Factories;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollAdjustmentSource;
use App\Enums\PayrollAdjustmentStatus;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending manual allowance adjustment of 250 for the next run.
 *
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'PA-'.str_pad((string) fake()->unique()->numberBetween(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'employee_id' => Employee::factory(),
            'source' => PayrollAdjustmentSource::Manual,
            'status' => PayrollAdjustmentStatus::Pending,
            'code' => 'ADJ',
            'name_ar' => 'تسوية',
            'name_en' => 'Adjustment',
            'kind' => PayComponentKind::Earning,
            'category' => PayComponentCategory::Allowance,
            'amount' => '250.00',
            'reason' => fake()->sentence(),
        ];
    }
}
