<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeEmergencyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An employee's first emergency contact.
 *
 * @extends Factory<EmployeeEmergencyContact>
 */
class EmployeeEmergencyContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'name' => fake()->name(),
            'relationship' => 'Spouse',
            'phone' => '+2011'.fake()->numerify('########'),
        ];
    }
}
