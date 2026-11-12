<?php

namespace Database\Factories;

use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A manual +5 day adjustment of this year's balance.
 *
 * @extends Factory<LeaveBalanceTransaction>
 */
class LeaveBalanceTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'leave_year' => (int) now()->format('Y'),
            'type' => LeaveTransactionType::Adjustment,
            'amount' => '5.00',
            'effective_date' => now()->toDateString(),
            'reason' => fake()->sentence(),
        ];
    }
}
