<?php

namespace App\Notifications\Automation;

use App\Notifications\CentreNotification;

/**
 * The notification of a scheduled automation (birthdays, anniversaries, probation, attendance alerts,
 * training reminders, approvals digest). The text is entirely the event's template; values are given
 * either as plain scalars or per language (['ar' => …, 'en' => …]) so a queued copy renders in the
 * recipient's language without reloading the records.
 */
class AutomationNotification extends CentreNotification
{
    /**
     * @param  array<string, scalar|array<string, scalar|null>|null>  $values
     * @param  array<string, scalar|null>  $data  stored ids (e.g. employee_id) and "url"
     */
    public function __construct(
        public readonly string $eventKey,
        public readonly array $values,
        public readonly array $data,
    ) {}

    public function event(): string
    {
        return $this->eventKey;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $locale ??= app()->getLocale();
        $resolved = [];

        foreach ($this->values as $name => $value) {
            $resolved[$name] = is_array($value) ? ($value[$locale] ?? reset($value) ?: null) : $value;
        }

        return $resolved;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function payload(object $notifiable): array
    {
        return ['kind' => $this->eventKey, ...$this->data];
    }
}
