<?php

namespace Database\Factories;

use App\Enums\LoanChangeType;
use App\Enums\RequestStatus;
use App\Models\EmployeeLoan;
use App\Models\LoanChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending request to skip a loan's next instalment.
 *
 * @extends Factory<LoanChange>
 */
class LoanChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_loan_id' => EmployeeLoan::factory(),
            'type' => LoanChangeType::Skip,
            'status' => RequestStatus::Pending,
            'reason' => fake()->sentence(),
        ];
    }
}
