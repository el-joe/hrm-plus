<?php

namespace App\Enums;

/**
 * Value types a system setting may declare in config/settings.php.
 */
enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    // Decimals stay strings end to end so money and rates never become floats.
    case Decimal = 'decimal';
    // A list of scalar values, stored as JSON.
    case List = 'list';

    /**
     * Convert a stored string back into its PHP value.
     */
    public function cast(?string $stored): mixed
    {
        if ($stored === null) {
            return null;
        }

        return match ($this) {
            self::String, self::Decimal => $stored,
            self::Integer => (int) $stored,
            self::Boolean => $stored === '1',
            self::List => json_decode($stored, true) ?? [],
        };
    }

    /**
     * Convert a PHP value into the string kept in the database.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String, self::Decimal => (string) $value,
            self::Integer => (string) (int) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::List => json_encode(array_values((array) $value), JSON_UNESCAPED_UNICODE),
        };
    }
}
