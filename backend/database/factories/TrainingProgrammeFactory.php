<?php

namespace Database\Factories;

use App\Models\TrainingProgramme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProgramme>
 */
class TrainingProgrammeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return ['code' => 'PG'.$number, 'name_ar' => 'برنامج '.$number, 'name_en' => 'Programme '.$number, 'is_active' => true];
    }
}
