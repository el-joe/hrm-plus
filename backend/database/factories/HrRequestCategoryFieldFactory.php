<?php

namespace Database\Factories;

use App\Enums\HrRequestFieldType;
use App\Models\HrRequestCategory;
use App\Models\HrRequestCategoryField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An optional text field on an HR request category.
 *
 * @extends Factory<HrRequestCategoryField>
 */
class HrRequestCategoryFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'hr_request_category_id' => HrRequestCategory::factory(),
            'key' => 'field_'.$number,
            'label_ar' => 'حقل '.$number,
            'label_en' => 'Field '.$number,
            'type' => HrRequestFieldType::Text,
            'is_required' => false,
        ];
    }
}
