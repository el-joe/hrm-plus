<?php

namespace App\Support;

/**
 * Display formatting of DECIMAL amounts without ever going through float:
 * "1234567.5" -> "1,234,567.50".
 */
class Money
{
    public static function format(string|int|null $amount, ?int $decimals = null): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $decimals ??= (int) setting('localisation.currency_decimals');
        $value = bcadd((string) $amount, '0', $decimals);
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-')), 2, '');
        $whole = strrev(implode(',', str_split(strrev($whole), 3)));

        return ($negative ? '-' : '').$whole.($decimals > 0 ? '.'.$fraction : '');
    }
}
