<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Channels\PushNotificationChannel;

/**
 * Decides the channels of one notification for one recipient:
 *
 * - database (in-app): the recipient's preference;
 * - mail: setting notifications.email_enabled, an e-mail address, and the preference;
 * - push: setting notifications.push_enabled, at least one registered device, and the preference.
 *
 * Mandatory events ignore preferences but still need the channel to be configured.
 */
class NotificationRouter
{
    public function __construct(private readonly NotificationPreferences $preferences) {}

    /**
     * @return list<string> Laravel channel names / classes
     */
    public function channelsFor(object $notifiable, string $event): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = [];

        if ($this->preferences->enabled($notifiable, $event, 'database')) {
            $channels[] = 'database';
        }

        if (setting('notifications.email_enabled') && filled($notifiable->email) && $this->preferences->enabled($notifiable, $event, 'mail')) {
            $channels[] = 'mail';
        }

        if (setting('notifications.push_enabled') && $this->preferences->enabled($notifiable, $event, 'push')
            && DeviceToken::query()->where('user_id', $notifiable->id)->exists()) {
            $channels[] = PushNotificationChannel::class;
        }

        return $channels;
    }
}
