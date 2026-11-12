<?php

namespace App\Services\Notifications\Push;

/**
 * A push notification for the mobile app: title, body and the data payload the app routes on.
 * Data values are strings (FCM requirement):
 *
 *   event            registry key (config/notification_events.php), e.g. "leave_approved"
 *   kind             the module's kind (usually equal to event)
 *   notification_id  id of the in-app notification (POST /api/v1/me/notifications/{id}/read)
 *   url              portal page of the record
 *   <record>_id      ids of the related record, e.g. leave_request_id, hr_request_id, announcement_id
 *
 * badge = the user's unread in-app count (iOS badge).
 */
final readonly class PushMessage
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
        public ?int $badge = null,
    ) {}
}
