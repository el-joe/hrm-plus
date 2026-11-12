<?php

namespace Database\Factories;

use App\Enums\LoanTransactionType;
use App\Models\EmployeeLoan;
use App\Models\LoanTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The disbursement of a 6000 loan.
 *
 * @extends Factory<LoanTransaction>
 */
class LoanTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_loan_id' => EmployeeLoan::factory(),
            'type' => LoanTransactionType::Disbursement,
            'amount' => '6000.00',
            'balance_after' => '6000.00',
            'occurred_on' => now()->toDateString(),
        ];
    }
}
