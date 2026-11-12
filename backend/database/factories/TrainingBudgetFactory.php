<?php

namespace Database\Factories;

use App\Models\TrainingBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A company-wide budget of the current year.
 *
 * @extends Factory<TrainingBudget>
 */
class TrainingBudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['year' => (int) now()->format('Y'), 'department_id' => null, 'amount' => '50000.00'];
    }
}
