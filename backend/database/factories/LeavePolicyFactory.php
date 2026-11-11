<?php

namespace Database\Factories;

use App\Enums\LeaveAccrualMethod;
use App\Enums\LeavePolicyScope;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A company-wide policy of 21 days a year credited up front, no carry-over.
 *
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'LP'.$number,
            'name_ar' => 'سياسة '.$number,
            'name_en' => 'Policy '.$number,
            'leave_type_id' => LeaveType::factory(),
            'scope_type' => LeavePolicyScope::Company,
            'scope_id' => null,
            'effective_from' => '2020-01-01',
            'effective_to' => null,
            'entitlement_days' => '21.00',
            'accrual_method' => LeaveAccrualMethod::AnnualUpfront,
            'service_tiers' => null,
            'carry_over_max_days' => '0.00',
            'carry_over_expiry_months' => null,
            'max_balance_days' => null,
            'allow_negative_balance' => false,
            'negative_limit_days' => null,
            'encashable' => false,
            'encashment_max_days' => null,
            'is_active' => true,
        ];
    }
}
