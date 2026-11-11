<?php

namespace App\Services\Settings;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Typed system settings. Definitions (type, default, validation) live in
 * config/settings.php; admin overrides live in the `settings` table and are
 * cached until the next write.
 *
 *     setting('attendance.late_grace_minutes');          // helper
 *     $settings->get('payroll.overtime_rate_multiplier'); // injected service
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.stored';

    /**
     * @return array<string, array<string, array{type: string, default: mixed, rules: list<mixed>, options?: list<string>}>>
     */
    public function definitions(): array
    {
        return config('settings');
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * Read one setting by its dotted "group.name" key.
     *
     * @throws InvalidArgumentException when the key is not declared in config/settings.php
     */
    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $stored = $this->stored();

        if (! array_key_exists($key, $stored)) {
            return $definition['default'];
        }

        return SettingType::from($definition['type'])->cast($stored[$key]);
    }

    /**
     * All settings of a group as name => value.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $values = [];

        foreach (array_keys($this->groupDefinitions($group)) as $name) {
            $values[$name] = $this->get($group.'.'.$name);
        }

        return $values;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * Persist several settings atomically, keyed by dotted key.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $type = SettingType::from($this->definition($key)['type']);

                // Go through the model (not upsert) so each change is audited.
                $setting = Setting::firstOrNew(['key' => $key]);
                $setting->fill(['group' => strtok($key, '.'), 'value' => $type->serialize($value)])->save();
            }
        });

        $this->flush();
    }

    /**
     * Validation rules for the admin settings form of one group, keyed by setting name.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $group): array
    {
        $rules = [];

        foreach ($this->groupDefinitions($group) as $name => $definition) {
            $isList = $definition['type'] === SettingType::List->value;
            $options = $definition['options'] ?? null;

            $rules[$name] = $definition['rules'];

            if ($options !== null && ! $isList) {
                $rules[$name][] = Rule::in($options);
            }

            if ($isList) {
                $rules[$name.'.*'] = $options !== null ? ['string', Rule::in($options)] : ['string', 'max:100'];
            }
        }

        return $rules;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{type: string, default: mixed, rules: list<mixed>, options?: list<string>}>
     */
    public function groupDefinitions(string $group): array
    {
        return $this->definitions()[$group] ?? throw new InvalidArgumentException("Unknown settings group [{$group}].");
    }

    /**
     * @return array{type: string, default: mixed, rules: list<mixed>, options?: list<string>}
     */
    private function definition(string $key): array
    {
        [$group, $name] = array_pad(explode('.', $key, 2), 2, '');

        return $this->definitions()[$group][$name] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /**
     * Stored overrides as key => raw string value.
     *
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
    }
}
