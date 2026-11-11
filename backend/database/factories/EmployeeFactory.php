<?php

namespace Database\Factories;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Employees come with their opening "hire" history row, like those created
 * through App\Actions\Employees\CreateEmployee.
 *
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 9_999_999);
        $hired = fake()->dateTimeBetween('-8 years', '-1 month')->format('Y-m-d');

        return [
            'employee_number' => 'T-'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
            'name_ar' => 'موظف '.$number,
            'name_en' => fake()->name(),
            'email' => 'employee'.$number.'@example.test',
            'phone' => '+2010'.fake()->numerify('########'),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-21 years')->format('Y-m-d'),
            'branch_id' => Branch::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->create(['branch_id' => $attributes['branch_id']]),
            'employment_type_id' => EmploymentType::factory(),
            'status' => EmployeeStatus::Active,
            'hire_date' => $hired,
            'original_hire_date' => $hired,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Employee $employee): void {
            $employee->assignments()->create([
                'change_type' => AssignmentChangeType::Hire,
                'effective_date' => $employee->hire_date,
                ...$employee->only(Employee::PLACEMENT_COLUMNS),
                'applied_at' => now(),
            ]);
        });
    }

    /**
     * Place the employee in a position (its department, branch, title and grade).
     */
    public function inPosition(Position $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position_id' => $position->id,
            'department_id' => $position->department_id,
            'branch_id' => $position->department->branch_id ?? Branch::factory(),
            'job_title_id' => $position->job_title_id,
            'job_grade_id' => $position->job_grade_id,
        ]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
            'branch_id' => $department->branch_id ?? Branch::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EmployeeStatus::Inactive]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Terminated,
            'termination_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
