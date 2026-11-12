<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The primary bank account of an employee (the IBAN hash is set by the model).
 *
 * @extends Factory<EmployeeBankAccount>
 */
class EmployeeBankAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'bank_name' => 'National Bank',
            'account_holder' => fake()->name(),
            'account_number' => fake()->numerify('############'),
            'iban' => 'EG'.fake()->numerify('###########################'),
            'swift_code' => 'NBEGEGCX',
            'is_primary' => true,
        ];
    }
}
