<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A stored setting override. Write real settings through App\Services\Settings\SettingsService.
 *
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'factory.setting_'.fake()->unique()->numberBetween(1, 9_999_999),
            'group' => 'factory',
            'value' => fake()->word(),
        ];
    }
}
