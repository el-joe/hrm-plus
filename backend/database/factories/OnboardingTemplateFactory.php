<?php

namespace Database\Factories;

use App\Models\OnboardingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active company-wide onboarding checklist without tasks.
 *
 * @extends Factory<OnboardingTemplate>
 */
class OnboardingTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name_ar' => 'قائمة '.$number,
            'name_en' => 'Checklist '.$number,
            'is_active' => true,
        ];
    }
}
