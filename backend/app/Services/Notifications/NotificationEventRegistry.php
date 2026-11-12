<?php

namespace App\Services\Notifications;

use App\Models\User;
use InvalidArgumentException;

/**
 * Reads config/notification_events.php: the events the notification centre knows, their module,
 * audience, placeholder whitelist, default texts and whether they are mandatory.
 */
class NotificationEventRegistry
{
    /**
     * @return array<string, array{module: string, audience: string, title: string, message: string, placeholders: list<string>, mandatory?: bool, off_by_default?: list<string>}>
     */
    public function all(): array
    {
        return config('notification_events.events');
    }

    public function has(string $event): bool
    {
        return isset($this->all()[$event]);
    }

    /**
     * @return array{module: string, audience: string, title: string, message: string, placeholders: list<string>, mandatory?: bool, off_by_default?: list<string>}
     */
    public function get(string $event): array
    {
        return $this->all()[$event] ?? throw new InvalidArgumentException("Unknown notification event [{$event}].");
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        return config('notification_events.channels');
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        return config('notification_events.modules');
    }

    /**
     * Placeholders a template of the event may use: the event's own plus the common ones.
     *
     * @return list<string>
     */
    public function placeholders(string $event): array
    {
        return array_values(array_unique([...config('notification_events.common'), ...$this->get($event)['placeholders']]));
    }

    public function isMandatory(string $event): bool
    {
        return (bool) ($this->get($event)['mandatory'] ?? false);
    }

    /**
     * Whether the channel is on for a user who never chose.
     */
    public function enabledByDefault(string $event, string $channel): bool
    {
        return ! in_array($channel, $this->get($event)['off_by_default'] ?? [], true);
    }

    public function label(string $event): string
    {
        return __('notifications.events.'.$event);
    }

    /**
     * Events a user may receive, grouped by module in display order: employees see employee events,
     * admins admin events, both see "both" events.
     *
     * @return array<string, list<string>> module => event keys
     */
    public function forUser(User $user): array
    {
        $audiences = $user->isAdmin() ? ['admin', 'both'] : ['employee', 'both'];

        if ($user->isAdmin() && $user->employee_id !== null) {
            $audiences[] = 'employee';
        }

        $grouped = array_fill_keys($this->modules(), []);

        foreach ($this->all() as $key => $event) {
            if (in_array($event['audience'], $audiences, true)) {
                $grouped[$event['module']][] = $key;
            }
        }

        return array_filter($grouped);
    }

    /**
     * Default text of an event for a channel and language, in template syntax ({{name}}), from the lang files.
     *
     * @return array{subject: string, body: string}
     */
    public function defaultText(string $event, string $locale): array
    {
        $definition = $this->get($event);

        return [
            'subject' => $this->toTemplateSyntax((string) trans($definition['title'], [], $locale), $event),
            'body' => $this->toTemplateSyntax((string) trans($definition['message'], [], $locale), $event),
        ];
    }

    /**
     * ":employee" (Laravel lang) → "{{employee}}" for whitelisted names only (other colons are text).
     */
    private function toTemplateSyntax(string $text, string $event): string
    {
        $names = $this->placeholders($event);
        usort($names, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($names as $name) {
            $text = preg_replace('/:'.preg_quote($name, '/').'(?![A-Za-z0-9_])/', '{{'.$name.'}}', $text) ?? $text;
        }

        return $text;
    }
}
