<?php

namespace Database\Factories;

use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A sent mail notification.
 *
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'event' => 'leave.request_approved',
            'notification_type' => 'App\\Notifications\\Leave\\LeaveRequestDecided',
            'channel' => 'mail',
            'status' => NotificationDeliveryStatus::Sent,
            'attempts' => 1,
            'recipient' => fake()->safeEmail(),
            'sent_at' => now(),
        ];
    }
}
