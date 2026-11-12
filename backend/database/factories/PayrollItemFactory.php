<?php

namespace Database\Factories;

use App\Enums\PayBasis;
use App\Enums\PayrollItemStatus;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A calculated item (6000 basic, no deductions) of an employee in a draft run, written
 * directly. Tests of payroll calculation should go through calculatedRun().
 *
 * @extends Factory<PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'payroll_period_id' => fn (array $attributes) => PayrollRun::query()->withoutGlobalScopes()->findOrFail($attributes['payroll_run_id'])->payroll_period_id,
            'employee_id' => Employee::factory(),
            'status' => PayrollItemStatus::Calculated,
            'branch_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->branch_id,
            'department_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->department_id,
            'currency' => 'USD',
            'pay_basis' => PayBasis::Monthly,
            'basic_salary' => '6000.00',
            'proration_factor' => '1.000000',
            'gross_pay' => '6000.00',
            'total_deductions' => '0.00',
            'net_pay' => '6000.00',
            'calculated_at' => now(),
        ];
    }
}
