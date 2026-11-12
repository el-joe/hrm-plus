<?php

namespace Database\Factories;

use App\Enums\StatutoryType;
use App\Models\StatutoryRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active social insurance rule set for Egypt, without versions.
 *
 * @extends Factory<StatutoryRuleSet>
 */
class StatutoryRuleSetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 9_999_999);

        return [
            'code' => 'SR'.$number,
            'name_ar' => 'تأمينات '.$number,
            'name_en' => 'Social insurance '.$number,
            'country_code' => 'EG',
            'type' => StatutoryType::SocialInsurance,
            'is_demo' => false,
            'is_active' => true,
        ];
    }
}
