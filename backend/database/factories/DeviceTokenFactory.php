<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An Android push token of a user.
 *
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = fake()->sha256().fake()->sha256();

        return [
            'user_id' => User::factory(),
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'device_name' => 'Pixel 8',
            'locale' => 'en',
            'last_seen_at' => now(),
        ];
    }
}
