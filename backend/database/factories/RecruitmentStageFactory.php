<?php

namespace Database\Factories;

use App\Enums\StageCategory;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active interview stage of a vacancy's own pipeline.
 *
 * @extends Factory<RecruitmentStage>
 */
class RecruitmentStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'vacancy_id' => Vacancy::factory(),
            'code' => 'stage_'.$number,
            'name_ar' => 'مرحلة '.$number,
            'name_en' => 'Stage '.$number,
            'category' => StageCategory::Interview,
            'sort_order' => 50,
            'notify_candidate' => false,
            'is_active' => true,
        ];
    }
}
