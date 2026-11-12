<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An employee's value of a text custom field.
 *
 * @extends Factory<EmployeeCustomFieldValue>
 */
class EmployeeCustomFieldValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'custom_field_id' => EmployeeCustomField::factory(),
            'value_text' => fake()->words(3, true),
        ];
    }
}
