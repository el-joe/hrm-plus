<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Notifications\DeliveryLog;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\NotificationTemplates;
use App\Services\Notifications\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Base of every notification sent to a user account (Phase 8b, notification centre).
 *
 * A subclass names its event (config/notification_events.php) and supplies the placeholder values and
 * the payload (ids, url, reason). This base then:
 *
 * - picks the channels from the recipient's preferences and the channel settings (NotificationRouter):
 *   database (in-app), mail, push (App\Notifications\Channels\PushNotificationChannel);
 * - renders the editable template of the event for each channel in the recipient's language
 *   (NotificationTemplates; locale fallback, then the default lang text);
 * - is queued with retries; e-mail / push sends land in the delivery log (DeliveryLog).
 *
 * The stored in-app data keeps the shape every module already used:
 * {kind, event, <ids>, title, message, reason?, url}.
 */
abstract class CentreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Attempts of each queued channel send.
     */
    public int $tries = 3;

    /**
     * The event key in config/notification_events.php.
     */
    abstract public function event(): string;

    /**
     * Values of the event's placeholders in the recipient's language.
     *
     * @return array<string, scalar|null>
     */
    abstract public function placeholders(object $notifiable, ?string $locale): array;

    /**
     * Data stored with the in-app notification and sent in the push data payload. Must contain "kind"
     * and "url"; usually the related record id ("leave_request_id" => 12) and "reason" when there is one.
     *
     * @return array<string, mixed>
     */
    abstract public function payload(object $notifiable): array;

    /**
     * Label of the e-mail button.
     */
    protected function actionLabelKey(): string
    {
        return 'notifications.mail.open';
    }

    /**
     * Seconds before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationRouter::class)->channelsFor($notifiable, $this->event());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $payload = $this->payload($notifiable);
        $rendered = $this->rendered($notifiable, 'database');

        return [
            'kind' => $payload['kind'] ?? $this->event(),
            'event' => $this->event(),
            ...$payload,
            'title' => $rendered['subject'],
            'message' => $rendered['body'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->localeOf($notifiable);
        $payload = $this->payload($notifiable);
        $rendered = $this->rendered($notifiable, 'mail');
        $mail = (new MailMessage)->subject($rendered['subject']);

        if ($notifiable instanceof User) {
            $mail->greeting(__('notifications.mail.greeting', ['name' => $notifiable->name], $locale));
        }

        foreach (preg_split('/\R{1,}/', $rendered['body']) ?: [] as $line) {
            if (trim($line) !== '') {
                $mail->line($line);
            }
        }

        $reason = $payload['reason'] ?? null;

        if (filled($reason) && ! str_contains($rendered['body'], (string) $reason)) {
            $mail->line(__('notifications.mail.reason', ['reason' => $reason], $locale));
        }

        if (filled($payload['url'] ?? null)) {
            $mail->action(__($this->actionLabelKey(), [], $locale), $payload['url']);
        }

        return $mail->salutation(__('notifications.mail.salutation', ['app' => config('app.name')], $locale));
    }

    public function toPush(object $notifiable): PushMessage
    {
        $rendered = $this->rendered($notifiable, 'push');
        $data = ['event' => $this->event(), 'notification_id' => (string) $this->id];

        foreach ($this->payload($notifiable) as $key => $value) {
            if (is_scalar($value) && $key !== 'reason') {
                $data[$key] = (string) $value;
            }
        }

        $badge = $notifiable instanceof User ? $notifiable->unreadNotifications()->count() : null;

        return new PushMessage($rendered['subject'], $rendered['body'], $data, $badge);
    }

    /**
     * The queued job gave up after its retries: mark the pending log rows as failed.
     */
    public function failed(Throwable $exception): void
    {
        app(DeliveryLog::class)->failed($this->id, $exception);
    }

    /**
     * @return array{subject: string, body: string}
     */
    protected function rendered(object $notifiable, string $channel): array
    {
        $locale = $this->localeOf($notifiable);
        $values = [
            'recipient' => $notifiable instanceof User ? $notifiable->name : '',
            'app_name' => (string) config('app.name'),
            ...$this->placeholders($notifiable, $locale),
        ];

        return app(NotificationTemplates::class)->render($this->event(), $channel, $locale, $values);
    }

    protected function localeOf(object $notifiable): ?string
    {
        return $notifiable instanceof User ? $notifiable->locale : null;
    }
}
