<?php

namespace Database\Factories;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A transfer row keeping the employee's current placement. Tests of placement changes
 * should go through App\Services\Employees\EmploymentHistory.
 *
 * @extends Factory<EmployeeAssignment>
 */
class EmployeeAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'change_type' => AssignmentChangeType::Transfer,
            'effective_date' => now()->toDateString(),
            'branch_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->branch_id,
            'department_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->department_id,
            'employment_type_id' => fn (array $attributes) => Employee::query()->findOrFail($attributes['employee_id'])->employment_type_id,
            'status' => EmployeeStatus::Active,
            'applied_at' => now(),
        ];
    }
}
