<?php

namespace Database\Factories;

use App\Enums\AssetEventType;
use App\Models\Asset;
use App\Models\AssetEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The "registered" event of an asset.
 *
 * @extends Factory<AssetEvent>
 */
class AssetEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'type' => AssetEventType::Registered,
            'occurred_on' => now()->toDateString(),
        ];
    }
}
