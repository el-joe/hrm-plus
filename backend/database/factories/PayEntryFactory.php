<?php

namespace Database\Factories;

use App\Enums\PayComponentKind;
use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending recurring fixed allowance of 500 for one employee. Tests of the approval flow
 * should go through App\Actions\Payroll\ManagePayEntry (see payEntry()).
 *
 * @extends Factory<PayEntry>
 */
class PayEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'PE-'.str_pad((string) fake()->unique()->numberBetween(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'kind' => PayComponentKind::Earning,
            'salary_component_id' => SalaryComponent::factory(),
            'target_type' => PayEntryTarget::Employee,
            'employee_id' => Employee::factory(),
            'calculation' => PayEntryCalculation::Fixed,
            'amount' => '500.00',
            'recurrence' => PayEntryRecurrence::Recurring,
            'start_date' => now()->startOfMonth()->toDateString(),
            'status' => RequestStatus::Pending,
            'reason' => fake()->sentence(),
        ];
    }
}
