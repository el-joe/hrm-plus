<?php

namespace Database\Factories;

use App\Enums\AuthArea;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginHistory>
 */
class LoginHistoryFactory extends Factory
{
    /**
     * Define the model's default state: a successful admin sign-in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'area' => AuthArea::Admin,
            'successful' => true,
            'failure_reason' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function failed(string $reason = 'invalid_credentials'): static
    {
        return $this->state(fn (array $attributes) => [
            'successful' => false,
            'failure_reason' => $reason,
        ]);
    }
}
