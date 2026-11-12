<?php

namespace App\Notifications\Announcements;

use App\Models\Announcement;
use App\Notifications\CentreNotification;
use App\Notifications\Channels\PushNotificationChannel;

/**
 * Sent once to every active employee in the audience when an announcement becomes visible. Event
 * "announcement_acknowledgement_required" (mandatory) when it needs an acknowledgement, otherwise
 * "announcement_published". Besides the recipient's preferences, the channels are limited by the
 * announcement's own switches: in-app always, e-mail only with notify_email, push only with notify_push.
 */
class AnnouncementPublishedNotification extends CentreNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    public function event(): string
    {
        return $this->announcement->requires_acknowledgement ? 'announcement_acknowledgement_required' : 'announcement_published';
    }

    protected function actionLabelKey(): string
    {
        return 'announcements.notifications.open';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter(parent::via($notifiable), fn (string $channel): bool => match ($channel) {
            'mail' => $this->announcement->notify_email,
            PushNotificationChannel::class => $this->announcement->notify_push,
            default => true,
        }));
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        return [
            'title' => $this->announcement->title($locale),
            'summary' => $this->announcement->summary($locale, 140),
            'type' => $this->announcement->type->label($locale),
        ];
    }

    /**
     * @return array{kind: string, announcement_id: int, requires_acknowledgement: bool, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => $this->event(),
            'announcement_id' => $this->announcement->id,
            'requires_acknowledgement' => $this->announcement->requires_acknowledgement,
            'url' => route('portal.announcements.show', $this->announcement->id),
        ];
    }
}
