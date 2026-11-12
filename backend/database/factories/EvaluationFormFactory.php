<?php

namespace Database\Factories;

use App\Models\EvaluationForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active interview evaluation form without criteria.
 *
 * @extends Factory<EvaluationForm>
 */
class EvaluationFormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name_ar' => 'نموذج '.$number,
            'name_en' => 'Form '.$number,
            'is_active' => true,
        ];
    }
}
