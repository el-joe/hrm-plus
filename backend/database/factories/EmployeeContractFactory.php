<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open-ended contract that started when the employee was hired.
 *
 * @extends Factory<EmployeeContract>
 */
class EmployeeContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'reference' => 'CT-'.fake()->unique()->numberBetween(1, 9_999_999),
            'contract_type' => ContractType::Permanent,
            'start_date' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->hire_date->toDateString(),
        ];
    }
}
