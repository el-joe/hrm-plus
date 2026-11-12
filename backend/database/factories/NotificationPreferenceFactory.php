<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A user's opt-out of one event on the mail channel.
 *
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event' => 'leave.request_approved',
            'channel' => 'mail',
            'enabled' => false,
        ];
    }
}
