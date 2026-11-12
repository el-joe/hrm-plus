<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return ['code' => 'SK'.$number, 'name_ar' => 'مهارة '.$number, 'name_en' => 'Skill '.$number, 'category' => 'technical', 'is_active' => true];
    }
}
