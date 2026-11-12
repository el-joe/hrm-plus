<?php

namespace Database\Factories;

use App\Enums\LoanSource;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending loan request of 6000 over 6 instalments from next month. Tests of the loan
 * lifecycle should go through App\Actions\Payroll\ManageLoan.
 *
 * @extends Factory<EmployeeLoan>
 */
class EmployeeLoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'LN-'.str_pad((string) fake()->unique()->numberBetween(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'employee_id' => Employee::factory(),
            'type' => LoanType::Loan,
            'status' => LoanStatus::Pending,
            'source' => LoanSource::Admin,
            'requested_amount' => '6000.00',
            'requested_instalments' => 6,
            'requested_start' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            'outstanding_amount' => '0.00',
            'reason' => fake()->sentence(),
        ];
    }
}
