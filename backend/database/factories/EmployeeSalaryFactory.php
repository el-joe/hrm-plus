<?php

namespace Database\Factories;

use App\Enums\PayBasis;
use App\Enums\SalaryChangeType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A recorded (not yet applied) initial salary row. Use App\Services\Employees\EmploymentHistory
 * to change an employee's salary.
 *
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'change_type' => SalaryChangeType::Initial,
            'effective_date' => now()->toDateString(),
            'basic_salary' => '6000.00',
            'pay_basis' => PayBasis::Monthly,
            'currency' => 'USD',
        ];
    }
}
