<?php

namespace App\Services\Notifications;

/**
 * Renders notification templates: plain text with {{name}} placeholders. No Blade, no expressions, no
 * HTML — values are inserted as text (each channel escapes on output). Names outside the allowed list
 * render as an empty string.
 */
class TemplateRenderer
{
    public const PATTERN = '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/';

    /**
     * @param  array<string, scalar|null>  $values
     * @param  list<string>  $allowed
     */
    public function render(string $template, array $values, array $allowed): string
    {
        $rendered = preg_replace_callback(self::PATTERN, function (array $match) use ($values, $allowed): string {
            $name = $match[1];

            return in_array($name, $allowed, true) ? (string) ($values[$name] ?? '') : '';
        }, $template) ?? '';

        // Collapse the gaps an empty placeholder leaves ("Reason: ." stays; double spaces go).
        return trim(preg_replace('/[ \t]{2,}/', ' ', $rendered) ?? $rendered);
    }

    /**
     * Placeholder names used in a template.
     *
     * @return list<string>
     */
    public function placeholdersIn(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Names used in the template that are not allowed.
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public function unknownPlaceholders(string $template, array $allowed): array
    {
        return array_values(array_diff($this->placeholdersIn($template), $allowed));
    }
}
