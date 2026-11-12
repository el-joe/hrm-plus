<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\EvaluationForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A criterion of weight 1 on an interview evaluation form.
 *
 * @extends Factory<EvaluationCriterion>
 */
class EvaluationCriterionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_form_id' => EvaluationForm::factory(),
            'name_ar' => 'معيار',
            'name_en' => ucfirst(fake()->words(2, true)),
            'weight' => 1,
        ];
    }
}
