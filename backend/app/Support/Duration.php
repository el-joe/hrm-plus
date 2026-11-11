<?php

namespace App\Support;

/**
 * Minutes as "7:30" (hours:minutes), the format used on attendance screens
 * and exports. Null / zero render as the given placeholder.
 */
class Duration
{
    public static function format(?int $minutes, string $empty = '—'): string
    {
        if ($minutes === null || $minutes === 0) {
            return $empty;
        }

        $sign = $minutes < 0 ? '-' : '';
        $minutes = abs($minutes);

        return $sign.intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
