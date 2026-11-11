<?php

namespace Database\Factories;

use App\Enums\LeaveUnit;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A paid, balance-tracked leave type counted in working days, approved through "leave_request".
 *
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'LV'.$number,
            'name_ar' => 'إجازة '.$number,
            'name_en' => 'Leave '.$number,
            'is_paid' => true,
            'affects_payroll' => false,
            'tracks_balance' => true,
            'unit' => LeaveUnit::Day,
            'requires_attachment' => false,
            'min_service_months' => 0,
            'notice_days' => 0,
            'allows_past_dates' => false,
            'counts_rest_days' => false,
            'counts_holidays' => false,
            'approval_workflow' => 'leave_request',
            'color' => 'chart-1',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Unpaid leave deducted by payroll, without a balance.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => ['is_paid' => false, 'affects_payroll' => true, 'tracks_balance' => false]);
    }
}
