<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Helpers for highly sensitive identifiers (national id, passport number,
 * IBAN). The value itself is stored encrypted (the `encrypted` cast); a keyed
 * hash of its normalised form is stored next to it so an exact-match search
 * ("find the employee with national id X") needs no decryption and the hash
 * is useless without the application key.
 */
class SensitiveValue
{
    /**
     * Upper-case, without spaces, dashes or dots: "29001 0112-34567" and "29001011234567" match.
     */
    public static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalised = Str::upper(preg_replace('/[\s\-.]+/u', '', $value) ?? '');

        return $normalised === '' ? null : $normalised;
    }

    public static function hash(?string $value): ?string
    {
        $normalised = self::normalise($value);

        return $normalised === null ? null : hash_hmac('sha256', $normalised, (string) config('app.key'));
    }

    /**
     * "••••••4567": only the last characters stay visible.
     */
    public static function mask(?string $value, int $visible = 4): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $length = mb_strlen($value);
        $visible = min($visible, max(0, $length - 2));

        return str_repeat('•', max(4, $length - $visible)).mb_substr($value, $length - $visible);
    }
}
