<?php

namespace Database\Factories;

use App\Enums\TrainingProviderType;
use App\Models\TrainingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProvider>
 */
class TrainingProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'TP'.$number,
            'name_ar' => 'جهة تدريب '.$number,
            'name_en' => 'Provider '.$number,
            'type' => TrainingProviderType::External,
            'is_active' => true,
        ];
    }
}
