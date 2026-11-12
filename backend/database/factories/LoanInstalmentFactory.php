<?php

namespace Database\Factories;

use App\Enums\LoanInstalmentStatus;
use App\Models\EmployeeLoan;
use App\Models\LoanInstalment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The first scheduled instalment of a loan (1000, due next month).
 *
 * @extends Factory<LoanInstalment>
 */
class LoanInstalmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_loan_id' => EmployeeLoan::factory(),
            'employee_id' => fn (array $attributes) => EmployeeLoan::query()->withoutGlobalScopes()->findOrFail($attributes['employee_loan_id'])->employee_id,
            'sequence' => 1,
            'due_date' => now()->addMonthNoOverflow()->endOfMonth()->toDateString(),
            'amount' => '1000.00',
            'paid_amount' => '0.00',
            'status' => LoanInstalmentStatus::Scheduled,
        ];
    }
}
