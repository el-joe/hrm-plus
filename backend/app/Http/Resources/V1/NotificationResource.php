<?php

namespace App\Http\Resources\V1;

use App\Services\Notifications\NotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * An in-app notification of the token owner (mobile app). "data" holds the related record ids
 * (e.g. leave_request_id) the app routes on; "url" is the web page (portal) of the same record.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = app(NotificationInbox::class)->present($this->resource);

        return [
            'id' => $item['id'],
            'event' => $item['event'],
            'kind' => $item['kind'],
            'module' => $item['module'],
            'title' => $item['title'],
            'message' => $item['message'],
            'data' => $item['data'],
            'url' => $item['url'],
            'is_read' => $item['read'],
            'read_at' => $item['read_at']?->toIso8601ZuluString(),
            'created_at' => $item['created_at']->toIso8601ZuluString(),
        ];
    }
}
