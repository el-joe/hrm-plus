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
     * turn the absence into an explicit false / empty list, and split a free list typed in a textarea.
     */
    protected function prepareForValidation(): void
    {
        $missing = [];

        foreach ($this->definitions() as $name => $definition) {
            if ($definition['type'] === SettingType::Secret->value && ! filled($this->input($name))) {
                // An empty secret field keeps the stored value.
                $this->request->remove($name);

                continue;
            }

            if ($definition['type'] === SettingType::List->value && ! isset($definition['options']) && $this->has($name) && ! is_array($this->input($name))) {
                // A free list is typed one item per line (commas and spaces also separate items).
                $missing[$name] = array_values(array_filter(
                    array_map(fn (string $item): string => rtrim($item, '/'), preg_split('/[\s,]+/', (string) $this->input($name)) ?: []),
                    fn (string $item): bool => $item !== '',
                ));

                continue;
            }

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
            $attributes[$name.'.*'] = $attributes[$name];
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

        foreach ($this->definitions() as $name => $definition) {
            $key = $this->route('group').'.'.$name;

            if ($definition['type'] === SettingType::Secret->value) {
                if (filled($this->validated($name))) {
                    $values[$key] = $this->validated($name);
                } elseif ($this->boolean($name.'_clear')) {
                    $values[$key] = null;
                }

                continue;
            }

            $values[$key] = $this->validated($name);
        }

        return $values;
    }

    /**
     * @return array<string, array{type: string, default: mixed, rules: list<mixed>, options?: list<string>, item_rules?: list<string>}>
     */
    private function definitions(): array
    {
        return app(SettingsService::class)->formDefinitions($this->route('group'));
    }
}
