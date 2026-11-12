<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editable notification texts (per event, channel and language).
 *
 * resolve(): the template used for a send — the stored row of the language, else the stored row of the
 * fallback language, else the default lang text (so a missing row never breaks a notification).
 * save() refuses placeholders outside the event's whitelist; reset() restores the default text.
 */
class NotificationTemplates
{
    /**
     * @var array<string, array{subject: string, body: string}>
     */
    private array $memo = [];

    public function __construct(
        private readonly NotificationEventRegistry $events,
        private readonly TemplateRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys(config('app.supported_locales'));
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function resolve(string $event, string $channel, ?string $locale): array
    {
        $locale = in_array($locale, $this->locales(), true) ? $locale : config('app.locale');
        $key = $event.'|'.$channel.'|'.$locale;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $rows = NotificationTemplate::query()->where('event', $event)->where('channel', $channel)->get()->keyBy('locale');
        $fallback = config('app.fallback_locale');
        $row = $rows->get($locale) ?? $rows->get($fallback);

        return $this->memo[$key] = $row !== null
            ? ['subject' => $row->subject, 'body' => $row->body]
            : $this->events->defaultText($event, $locale);
    }

    /**
     * Render an event's template with the given placeholder values.
     *
     * @param  array<string, scalar|null>  $values
     * @return array{subject: string, body: string}
     */
    public function render(string $event, string $channel, ?string $locale, array $values): array
    {
        $template = $this->resolve($event, $channel, $locale);
        $allowed = $this->events->placeholders($event);

        return [
            'subject' => $this->renderer->render($template['subject'], $values, $allowed),
            'body' => $this->renderer->render($template['body'], $values, $allowed),
        ];
    }

    /**
     * Render arbitrary (unsaved) text with sample values — the preview of the edit screen.
     *
     * @return array{subject: string, body: string}
     */
    public function preview(string $event, string $subject, string $body, string $locale): array
    {
        $values = $this->sampleValues($event, $locale);
        $allowed = $this->events->placeholders($event);

        return [
            'subject' => $this->renderer->render($subject, $values, $allowed),
            'body' => $this->renderer->render($body, $values, $allowed),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sampleValues(string $event, string $locale): array
    {
        $values = [];

        foreach ($this->events->placeholders($event) as $name) {
            $key = 'notifications.samples.'.$name;
            $values[$name] = trans()->has($key, $locale) ? (string) trans($key, [], $locale) : '['.$name.']';
        }

        $values['app_name'] = (string) config('app.name');

        return $values;
    }

    /**
     * @throws ValidationException when the text uses a placeholder the event does not offer
     */
    public function save(string $event, string $channel, string $locale, string $subject, string $body, User $editor): NotificationTemplate
    {
        $allowed = $this->events->placeholders($event);
        $unknown = array_values(array_unique([
            ...$this->renderer->unknownPlaceholders($subject, $allowed),
            ...$this->renderer->unknownPlaceholders($body, $allowed),
        ]));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => __('notifications.templates.unknown_placeholders', ['names' => implode(', ', array_map(fn (string $name): string => '{{'.$name.'}}', $unknown))]),
            ]);
        }

        $default = $this->events->defaultText($event, $locale);
        $template = NotificationTemplate::query()->firstOrNew(['event' => $event, 'channel' => $channel, 'locale' => $locale]);
        $template->fill([
            'subject' => $subject,
            'body' => $body,
            'is_customised' => $subject !== $default['subject'] || $body !== $default['body'],
            'updated_by' => $editor->id,
        ])->save();

        $this->memo = [];

        return $template;
    }

    /**
     * Restore the default text of an event (every channel and language, or one of them).
     */
    public function reset(string $event, User $editor, ?string $channel = null, ?string $locale = null): void
    {
        DB::transaction(function () use ($event, $editor, $channel, $locale): void {
            foreach ($this->events->channels() as $candidateChannel) {
                foreach ($this->locales() as $candidateLocale) {
                    if (($channel !== null && $channel !== $candidateChannel) || ($locale !== null && $locale !== $candidateLocale)) {
                        continue;
                    }

                    $default = $this->events->defaultText($event, $candidateLocale);
                    NotificationTemplate::query()->updateOrCreate(
                        ['event' => $event, 'channel' => $candidateChannel, 'locale' => $candidateLocale],
                        [...$default, 'is_customised' => false, 'updated_by' => $editor->id],
                    );
                }
            }

            $this->audit->log('notification_template.reset', meta: ['event' => $event, 'channel' => $channel, 'locale' => $locale], actor: $editor);
        });

        $this->memo = [];
    }

    /**
     * Create the missing rows from the default lang texts (seeder; never overwrites an edited text).
     *
     * @return int rows created
     */
    public function seedDefaults(): int
    {
        $existing = NotificationTemplate::query()->get(['event', 'channel', 'locale'])
            ->map(fn (NotificationTemplate $row): string => $row->event.'|'.$row->channel.'|'.$row->locale)->flip();
        $created = 0;

        foreach (array_keys($this->events->all()) as $event) {
            foreach ($this->locales() as $locale) {
                $default = $this->events->defaultText($event, $locale);

                foreach ($this->events->channels() as $channel) {
                    if ($existing->has($event.'|'.$channel.'|'.$locale)) {
                        continue;
                    }

                    NotificationTemplate::query()->create([...$default, 'event' => $event, 'channel' => $channel, 'locale' => $locale, 'is_customised' => false]);
                    $created++;
                }
            }
        }

        $this->memo = [];

        return $created;
    }

    public function flush(): void
    {
        $this->memo = [];
    }
}
