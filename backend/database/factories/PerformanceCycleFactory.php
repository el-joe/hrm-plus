<?php

namespace Database\Factories;

use App\Enums\PerformanceCycleType;
use App\Models\PerformanceCycle;
use App\Models\RatingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft semi-annual cycle (H2 2026, 70 % goals / 30 % competencies, self-assessment on).
 *
 * @extends Factory<PerformanceCycle>
 */
class PerformanceCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'CYC'.$number,
            'name_ar' => 'دورة '.$number,
            'name_en' => 'Cycle '.$number,
            'type' => PerformanceCycleType::SemiAnnual,
            'period_start' => '2026-07-01',
            'period_end' => '2026-12-31',
            'rating_scale_id' => RatingScale::factory(),
            'goals_weight' => '70.00',
            'competencies_weight' => '30.00',
            'self_assessment_enabled' => true,
        ];
    }
}
