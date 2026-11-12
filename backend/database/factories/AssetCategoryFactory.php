<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCategory>
 */
class AssetCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(10, 99999);

        return [
            'code' => 'CAT'.$number,
            'name_ar' => 'فئة '.$number,
            'name_en' => 'Category '.$number,
            'requires_serial' => false,
            'is_active' => true,
        ];
    }

    public function requiresSerial(): static
    {
        return $this->state(fn (array $attributes) => ['requires_serial' => true]);
    }
}
