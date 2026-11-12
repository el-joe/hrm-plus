<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Central display formatter (Phase 8c): applies the localisation settings
 * "date_format", "time_format" and "use_arabic_digits" (Settings → Localisation).
 *
 * - Dates / times use the configured PHP formats; instants are shown in the
 *   given timezone (default: the company timezone setting).
 * - Arabic-Indic digits (٠١٢٣٤٥٦٧٨٩) are used only while the interface language is
 *   Arabic AND the setting is on; machine-readable outputs (CSV / Excel cells,
 *   URLs, form values) must not go through digits().
 * - Amounts never go through float: money() / number() use bcmath strings.
 *
 * Blade: @fmtDate($value), @fmtDateTime($value), @fmtNumber($value), @fmtMoney($value) and the
 * <x-date> / <x-number> components. JavaScript: resources/js/lib/format.js reads the same
 * settings from <html data-digits data-date-format>.
 */
class Formatter
{
    private const WESTERN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function usesArabicDigits(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === 'ar' && (bool) setting('localisation.use_arabic_digits');
    }

    public static function dateFormat(): string
    {
        return (string) (setting('localisation.date_format') ?: 'Y-m-d');
    }

    public static function timeFormat(): string
    {
        return (string) (setting('localisation.time_format') ?: 'H:i');
    }

    /**
     * Western digits → Arabic-Indic digits when the setting applies.
     */
    public static function digits(string|int|float|null $value, ?string $locale = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;

        return self::usesArabicDigits($locale) ? str_replace(self::WESTERN, self::ARABIC_INDIC, $value) : $value;
    }

    public static function date(DateTimeInterface|string|null $value, ?string $locale = null): string
    {
        $date = self::parse($value);

        return $date === null ? '' : self::digits($date->format(self::dateFormat()), $locale);
    }

    /**
     * A date in the machine form of the setting (no digit conversion), for spreadsheet cells.
     */
    public static function plainDate(DateTimeInterface|string|null $value): string
    {
        $date = self::parse($value);

        return $date === null ? '' : $date->format(self::dateFormat());
    }

    public static function time(DateTimeInterface|string|null $value, ?string $timezone = null, ?string $locale = null): string
    {
        $instant = self::parse($value);

        if ($instant === null) {
            return '';
        }

        $formatted = $instant->setTimezone($timezone ?? self::timezone())->format(self::timeFormat());

        return self::digits(self::localiseMeridiem($formatted, $locale), $locale);
    }

    public static function dateTime(DateTimeInterface|string|null $value, ?string $timezone = null, ?string $locale = null): string
    {
        $instant = self::parse($value);

        if ($instant === null) {
            return '';
        }

        $local = $instant->setTimezone($timezone ?? self::timezone());
        $formatted = $local->format(self::dateFormat()).' '.self::localiseMeridiem($local->format(self::timeFormat()), $locale);

        return self::digits($formatted, $locale);
    }

    /**
     * Grouped number: ints / numeric strings (bcmath, never float for strings).
     */
    public static function number(string|int|float|null $value, int $decimals = 0, ?string $locale = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = is_string($value) && is_numeric($value)
            ? Money::format($value, $decimals)
            : number_format((float) $value, $decimals);

        return self::digits($formatted, $locale);
    }

    public static function money(string|int|null $value, ?int $decimals = null, ?string $locale = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::digits(Money::format((string) $value, $decimals), $locale);
    }

    public static function percent(string|int|float|null $value, int $decimals = 1, ?string $locale = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::digits(number_format((float) $value, $decimals).'%', $locale);
    }

    public static function timezone(): string
    {
        return (string) (setting('localisation.timezone') ?: config('app.timezone'));
    }

    private static function localiseMeridiem(string $formatted, ?string $locale): string
    {
        if (($locale ?? app()->getLocale()) !== 'ar') {
            return $formatted;
        }

        return str_replace(['AM', 'PM'], ['ص', 'م'], $formatted);
    }

    private static function parse(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
