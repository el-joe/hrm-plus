<?php

namespace Database\Factories;

use App\Models\RatingScale;
use App\Models\RatingScaleLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A sixth level above the five a RatingScaleFactory scale comes with.
 *
 * @extends Factory<RatingScaleLevel>
 */
class RatingScaleLevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating_scale_id' => RatingScale::factory(),
            'value' => '6.00',
            'label_ar' => 'استثنائي',
            'label_en' => 'Exceptional',
            'min_score' => '5.50',
            'color' => 'chart-1',
        ];
    }
}
