<?php

namespace App\Http\Requests\Admin;

use App\Enums\SettingType;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    /**
     * An unchecked checkbox and an empty multi-select send nothing at all;
     * turn the absence into an explicit false / empty list.
     */
    protected function prepareForValidation(): void
    {
        $missing = [];

        foreach ($this->definitions() as $name => $definition) {
            if ($this->has($name)) {
                continue;
            }

            if ($definition['type'] === SettingType::Boolean->value) {
                $missing[$name] = false;
            } elseif ($definition['type'] === SettingType::List->value) {
                $missing[$name] = [];
            }
        }

        $this->merge($missing);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return app(SettingsService::class)->rules($this->route('group'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (array_keys($this->definitions()) as $name) {
            $attributes[$name] = __('settings.keys.'.$this->route('group').'.'.$name);
        }

        return $attributes;
    }

    /**
     * The validated values keyed by full dotted setting key.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $values = [];

        foreach (array_keys($this->definitions()) as $name) {
            $values[$this->route('group').'.'.$name] = $this->validated($name);
        }

        return $values;
    }

    /**
     * @return array<string, array{type: string, default: mixed, rules: list<mixed>, options?: list<string>}>
     */
    private function definitions(): array
    {
        return app(SettingsService::class)->groupDefinitions($this->route('group'));
    }
}
