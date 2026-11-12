<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An available asset in good condition. Status changes in tests go through the actions.
 *
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999999);

        return [
            'asset_tag' => 'AS-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'asset_category_id' => AssetCategory::factory(),
            'name' => 'Laptop '.$number,
            'brand' => 'Dell',
            'model' => 'Latitude 5440',
            'serial_number' => 'SN'.$number,
            'purchase_date' => '2025-01-15',
            'purchase_cost' => '42000.00',
            'branch_id' => Branch::factory(),
            'condition' => 'good',
        ];
    }

    public function withoutBranch(): static
    {
        return $this->state(fn (array $attributes) => ['branch_id' => null]);
    }
}
