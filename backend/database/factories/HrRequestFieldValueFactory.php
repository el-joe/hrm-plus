<?php

namespace Database\Factories;

use App\Models\HrRequest;
use App\Models\HrRequestFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A submitted value of an HR request (labels are snapshots).
 *
 * @extends Factory<HrRequestFieldValue>
 */
class HrRequestFieldValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hr_request_id' => HrRequest::factory(),
            'label_ar' => 'التفاصيل',
            'label_en' => 'Details',
            'value' => fake()->sentence(),
        ];
    }
}
