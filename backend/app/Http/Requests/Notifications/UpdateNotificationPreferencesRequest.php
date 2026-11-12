<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * preferences[event][channel] = 0|1 (web form: a hidden 0 before each checkbox; API: JSON booleans).
 * Unknown events / channels and mandatory events are ignored by NotificationPreferences::save().
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'max:200'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function choices(): array
    {
        return collect((array) $this->validated('preferences'))
            ->map(fn (array $channels): array => array_map(fn ($value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN), $channels))
            ->all();
    }
}
