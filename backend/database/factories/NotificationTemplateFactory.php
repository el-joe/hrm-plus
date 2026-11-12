<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A customised English mail template of an event.
 *
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'factory.event_'.fake()->unique()->numberBetween(1, 999_999),
            'channel' => 'mail',
            'locale' => 'en',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_customised' => true,
        ];
    }
}
