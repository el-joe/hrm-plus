<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\CentreNotification;
use App\Services\Notifications\DeliveryLog;
use App\Services\Notifications\Push\PushChannelResolver;
use RuntimeException;

/**
 * Laravel notification channel for mobile push. Sends CentreNotification::toPush() to every registered
 * device of the user through the configured provider (FCM HTTP v1 or simulated), deletes tokens the
 * provider reports as unregistered, and records the outcome in the delivery log. A transient provider
 * failure throws so the queued job retries.
 */
class PushNotificationChannel
{
    public function __construct(private readonly PushChannelResolver $providers, private readonly DeliveryLog $log) {}

    public function send(object $notifiable, CentreNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $devices = DeviceToken::query()->where('user_id', $notifiable->id)->get();
        $provider = $this->providers->current();
        $result = $provider->send($devices, $notification->toPush($notifiable));

        if ($result->invalidDeviceIds !== []) {
            DeviceToken::query()->whereKey($result->invalidDeviceIds)->delete();
        }

        $this->log->push($notifiable, $notification, $result, $provider->name());

        if ($result->retryable) {
            throw new RuntimeException('Push delivery failed: '.$result->error);
        }
    }
}
