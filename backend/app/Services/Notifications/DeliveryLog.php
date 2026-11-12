<?php

namespace App\Services\Notifications;

use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\CentreNotification;
use App\Notifications\Channels\PushNotificationChannel;
use App\Services\Notifications\Push\PushResult;
use Illuminate\Support\Str;
use Throwable;

/**
 * The delivery log of e-mail and push sends of centre notifications (in-app rows are the notification
 * itself). One row per notification id, recipient and channel; each queued attempt increments attempts.
 *
 *   sending → sent | simulated | skipped | failed
 *
 * Filled by App\Listeners\RecordNotificationDelivery (NotificationSending / NotificationSent), by the push
 * channel (provider outcome) and by CentreNotification::failed() once the retries are exhausted.
 */
class DeliveryLog
{
    public const LOGGED_CHANNELS = ['mail', 'push'];

    public static function channelKey(string $channel): string
    {
        return $channel === PushNotificationChannel::class ? 'push' : $channel;
    }

    public function sending(object $notifiable, object $notification, string $channel): void
    {
        $row = $this->row($notifiable, $notification, $channel);

        if ($row === null) {
            return;
        }

        $row->fill([
            'status' => NotificationDeliveryStatus::Sending,
            'attempts' => $row->attempts + 1,
            'recipient' => $this->recipient($notifiable, self::channelKey($channel)),
        ])->save();
    }

    public function sent(object $notifiable, object $notification, string $channel): void
    {
        $row = $this->row($notifiable, $notification, $channel);

        // The push channel already stored its own outcome (sent / simulated / skipped).
        if ($row === null || $row->status !== NotificationDeliveryStatus::Sending) {
            return;
        }

        $row->fill(['status' => NotificationDeliveryStatus::Sent, 'sent_at' => now(), 'error' => null,
            'provider' => self::channelKey($channel) === 'mail' ? (string) config('mail.default') : $row->provider])->save();
    }

    public function push(object $notifiable, object $notification, PushResult $result, string $provider): void
    {
        $row = $this->row($notifiable, $notification, PushNotificationChannel::class);

        if ($row === null) {
            return;
        }

        $status = match (true) {
            $result->sent === 0 && $result->failed === 0 => NotificationDeliveryStatus::Skipped,
            $result->simulated => NotificationDeliveryStatus::Simulated,
            $result->sent > 0 => NotificationDeliveryStatus::Sent,
            default => NotificationDeliveryStatus::Failed,
        };

        $row->fill([
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $result->messageIds[0] ?? null,
            'recipient' => trans_choice('notifications.deliveries.devices', $result->sent + $result->failed, ['count' => $result->sent + $result->failed]),
            'error' => $result->error !== null ? Str::limit($result->error, 990) : null,
            'sent_at' => in_array($status, [NotificationDeliveryStatus::Sent, NotificationDeliveryStatus::Simulated], true) ? now() : null,
            'failed_at' => $status === NotificationDeliveryStatus::Failed ? now() : null,
        ])->save();
    }

    /**
     * The queued job gave up: every row of the notification still "sending" failed.
     */
    public function failed(?string $notificationId, Throwable $exception): void
    {
        if ($notificationId === null) {
            return;
        }

        NotificationDelivery::query()->where('notification_id', $notificationId)->where('status', NotificationDeliveryStatus::Sending)
            ->update(['status' => NotificationDeliveryStatus::Failed, 'failed_at' => now(), 'error' => Str::limit(class_basename($exception).': '.$exception->getMessage(), 990)]);
    }

    private function row(object $notifiable, object $notification, string $channel): ?NotificationDelivery
    {
        $key = self::channelKey($channel);

        if (! $notification instanceof CentreNotification || ! in_array($key, self::LOGGED_CHANNELS, true) || $notification->id === null) {
            return null;
        }

        $userId = $notifiable instanceof User ? $notifiable->id : null;

        return NotificationDelivery::query()->firstOrNew(
            ['notification_id' => $notification->id, 'channel' => $key, 'user_id' => $userId],
            ['event' => $notification->event(), 'notification_type' => $notification::class, 'status' => NotificationDeliveryStatus::Sending, 'attempts' => 0],
        );
    }

    private function recipient(object $notifiable, string $channel): ?string
    {
        if ($channel === 'mail') {
            return $notifiable instanceof User ? $notifiable->email : null;
        }

        return null;
    }
}
