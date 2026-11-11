<?php

namespace Database\Factories;

use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayRounding;
use App\Enums\StatutoryType;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A fixed earning of 100.00 per period, taxable, prorated, no approval.
 *
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'C'.$number,
            'name_ar' => 'مكوّن '.$number,
            'name_en' => 'Component '.$number,
            'kind' => PayComponentKind::Earning,
            'category' => PayComponentCategory::Allowance,
            'calculation_type' => PayCalculationType::Fixed,
            'amount' => '100.0000',
            'rate' => null,
            'is_taxable' => true,
            'is_insurable' => false,
            'prorate_partial_period' => true,
            'requires_approval' => false,
            'show_on_payslip' => true,
            'rounding' => PayRounding::HalfUp,
            'rounding_precision' => null,
            'display_order' => $number % 1000,
            'is_active' => true,
        ];
    }

    public function basic(): static
    {
        return $this->state(fn () => ['code' => 'BASIC', 'category' => PayComponentCategory::Basic, 'calculation_type' => PayCalculationType::Basic, 'amount' => null, 'is_insurable' => true]);
    }

    public function deduction(): static
    {
        return $this->state(fn () => ['kind' => PayComponentKind::Deduction, 'category' => PayComponentCategory::Other, 'is_taxable' => false]);
    }

    /**
     * A percentage of the basic salary (or of $base).
     */
    public function percentage(string $rate, ?SalaryComponent $base = null): static
    {
        return $this->state(fn () => ['calculation_type' => PayCalculationType::Percentage, 'amount' => null, 'rate' => $rate, 'base_component_id' => $base?->id]);
    }

    /**
     * No default amount: the amount comes from each entry.
     */
    public function variable(): static
    {
        return $this->state(fn () => ['calculation_type' => PayCalculationType::Variable, 'amount' => null]);
    }

    public function statutory(StatutoryType $type): static
    {
        return $this->state(fn () => ['kind' => PayComponentKind::Deduction, 'calculation_type' => PayCalculationType::Statutory, 'statutory_type' => $type, 'amount' => null]);
    }

    public function needsApproval(): static
    {
        return $this->state(fn () => ['requires_approval' => true]);
    }
}
