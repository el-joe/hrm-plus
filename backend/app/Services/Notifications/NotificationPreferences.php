<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Per-user notification choices (event × channel). No stored row = the event default; mandatory events
 * are always on whatever is stored.
 */
class NotificationPreferences
{
    /**
     * @var array<int, array<string, bool>>
     */
    private array $cache = [];

    public function __construct(private readonly NotificationEventRegistry $events, private readonly AuditLogger $audit) {}

    public function enabled(User $user, string $event, string $channel): bool
    {
        if ($this->events->isMandatory($event)) {
            return true;
        }

        $stored = $this->stored($user)[$event.'|'.$channel] ?? null;

        return $stored ?? $this->events->enabledByDefault($event, $channel);
    }

    /**
     * The preference screen data: module => event => channel => state.
     *
     * @return array<string, array<string, array{label: string, mandatory: bool, channels: array<string, bool>}>>
     */
    public function matrix(User $user): array
    {
        $matrix = [];

        foreach ($this->events->forUser($user) as $module => $keys) {
            foreach ($keys as $event) {
                $channels = [];

                foreach ($this->events->channels() as $channel) {
                    $channels[$channel] = $this->enabled($user, $event, $channel);
                }

                $matrix[$module][$event] = [
                    'label' => $this->events->label($event),
                    'mandatory' => $this->events->isMandatory($event),
                    'channels' => $channels,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Save the user's choices. Unknown events, events the user cannot receive and mandatory events are ignored.
     *
     * @param  array<string, array<string, bool>>  $choices  event => channel => enabled
     */
    public function save(User $user, array $choices): void
    {
        $allowed = collect($this->events->forUser($user))->flatten()->all();
        $changes = [];

        DB::transaction(function () use ($user, $choices, $allowed, &$changes): void {
            foreach ($allowed as $event) {
                if ($this->events->isMandatory($event) || ! array_key_exists($event, $choices)) {
                    continue;
                }

                foreach ($this->events->channels() as $channel) {
                    if (! array_key_exists($channel, $choices[$event])) {
                        continue;
                    }

                    $enabled = (bool) $choices[$event][$channel];

                    if ($enabled === $this->enabled($user, $event, $channel)) {
                        continue;
                    }

                    NotificationPreference::query()->updateOrCreate(
                        ['user_id' => $user->id, 'event' => $event, 'channel' => $channel],
                        ['enabled' => $enabled],
                    );
                    $changes[] = $event.'.'.$channel.'='.($enabled ? 'on' : 'off');
                }
            }
        });

        unset($this->cache[$user->id]);

        if ($changes !== []) {
            $this->audit->log('notification_preferences.updated', $user, meta: ['changes' => $changes], actor: $user);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function stored(User $user): array
    {
        return $this->cache[$user->id] ??= NotificationPreference::query()->where('user_id', $user->id)->get()
            ->mapWithKeys(fn (NotificationPreference $row): array => [$row->event.'|'.$row->channel => $row->enabled])->all();
    }
}
