<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A user's in-app notifications (the notification centre: bell, list page, API). Only ever the signed-in
 * user's own rows. Stored data keeps each module's shape: {kind, event, title, message, url, ...ids}.
 */
class NotificationInbox
{
    public function __construct(private readonly NotificationEventRegistry $events) {}

    /**
     * @return Builder<DatabaseNotification>
     */
    public function query(User $user, bool $unreadOnly = false): Builder
    {
        return DatabaseNotification::query()
            ->whereMorphedTo('notifiable', $user)
            ->when($unreadOnly, fn (Builder $query) => $query->whereNull('read_at'))
            ->latest()->orderByDesc('id');
    }

    public function unreadCount(User $user): int
    {
        return $this->query($user, true)->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function latest(User $user, int $limit = 6): Collection
    {
        return $this->query($user)->limit($limit)->get()->map(fn (DatabaseNotification $notification): array => $this->present($notification));
    }

    public function find(User $user, string $id): ?DatabaseNotification
    {
        return $this->query($user)->whereKey($id)->first();
    }

    public function markRead(DatabaseNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    public function markAllRead(User $user): int
    {
        return $this->query($user, true)->update(['read_at' => now()]);
    }

    /**
     * @return array{id: string, event: ?string, kind: ?string, module: ?string, title: string, message: string, url: ?string, data: array<string, mixed>, read: bool, read_at: ?Carbon, created_at: Carbon}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;
        $event = $data['event'] ?? null;
        $module = $event !== null && $this->events->has($event) ? $this->events->get($event)['module'] : null;

        return [
            'id' => $notification->id,
            'event' => $event,
            'kind' => $data['kind'] ?? null,
            'module' => $module,
            'title' => (string) ($data['title'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'url' => $this->safeUrl($data['url'] ?? null),
            'data' => array_diff_key($data, array_flip(['title', 'message', 'url', 'kind', 'event', 'reason'])),
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }

    /**
     * Only links into this application are followed (no open redirect through stored data).
     */
    public function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host !== null && ($host === $appHost || $host === request()->getHost()) ? $url : null;
    }
}
