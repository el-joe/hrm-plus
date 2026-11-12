<?php

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomFieldOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An option of a select custom field.
 *
 * @extends Factory<EmployeeCustomFieldOption>
 */
class EmployeeCustomFieldOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'custom_field_id' => EmployeeCustomField::factory()->state(['type' => CustomFieldType::Select]),
            'value' => 'option_'.$number,
            'label_ar' => 'خيار '.$number,
            'label_en' => 'Option '.$number,
        ];
    }
}
