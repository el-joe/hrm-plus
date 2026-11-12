<?php

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\EmployeeCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An optional, active text field hidden from the employee.
 *
 * @extends Factory<EmployeeCustomField>
 */
class EmployeeCustomFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 999_999);

        return [
            'code' => 'field_'.$number,
            'name_ar' => 'حقل '.$number,
            'name_en' => 'Field '.$number,
            'type' => CustomFieldType::Text,
            'is_required' => false,
            'visible_to_employee' => false,
            'is_active' => true,
        ];
    }
}
