<?php

namespace App\Http\Resources\V1;

use App\Models\Announcement;
use App\Services\Announcements\AnnouncementFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An announcement as the employee sees it (mobile app). The list item has no body; the detail
 * (withBody()) adds the sanitised HTML body, a plain-text version and the attachments.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    private bool $withBody = false;

    public function withBody(): static
    {
        $this->withBody = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $read = AnnouncementFeed::readOf($this->resource);
        $data = [
            'id' => $this->id,
            'title' => $this->title(),
            'summary' => $this->summary(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_pinned' => $this->is_pinned,
            'requires_acknowledgement' => $this->requires_acknowledgement,
            'published_at' => ($this->publish_at ?? $this->published_at)?->toIso8601ZuluString(),
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'is_read' => $read !== null,
            'read_at' => $read?->read_at?->toIso8601ZuluString(),
            'acknowledged' => $read?->acknowledged_at !== null,
            'acknowledged_at' => $read?->acknowledged_at?->toIso8601ZuluString(),
            'can_acknowledge' => $this->requires_acknowledgement && $read?->acknowledged_at === null,
            'attachments_count' => $this->attachments_count ?? $this->attachments->count(),
        ];

        if ($this->withBody) {
            $data['body_html'] = $this->body();
            $data['body_text'] = $this->bodyText();
            $data['attachments'] = $this->attachments->map(fn ($attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->file_name,
                'mime' => $attachment->file_mime,
                'size' => $attachment->file_size,
                'download_url' => route('api.v1.me.announcements.attachments.show', [$this->id, $attachment->id]),
            ])->values()->all();
        }

        return $data;
    }
}
