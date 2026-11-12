<?php

namespace Database\Factories;

use App\Enums\CompetencyCategory;
use App\Models\Competency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competency>
 */
class CompetencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return ['code' => 'CMP'.$number, 'name_ar' => 'كفاءة '.$number, 'name_en' => 'Competency '.$number, 'category' => CompetencyCategory::Core, 'is_active' => true];
    }
}
