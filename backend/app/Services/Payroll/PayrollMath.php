<?php

namespace App\Services\Payroll;

use App\Enums\PayRounding;

/**
 * bcmath helpers of the payroll layer. Every amount is a numeric string;
 * floats are never used. Intermediate results keep SCALE decimals and are
 * rounded once, at the end, to the component's precision.
 */
final class PayrollMath
{
    public const SCALE = 6;

    /**
     * Round a non-negative or negative amount to the precision with the given mode.
     */
    public static function round(string $value, int $precision, PayRounding $mode = PayRounding::HalfUp): string
    {
        $negative = bccomp($value, '0', self::SCALE) < 0;
        $absolute = $negative ? bcmul($value, '-1', self::SCALE) : bcadd($value, '0', self::SCALE);
        $step = bcpow('10', (string) -$precision, max($precision, 0) + 1);
        $truncated = bcadd($absolute, '0', $precision);
        $remainder = bcsub($absolute, $truncated, self::SCALE);

        $rounded = match ($mode) {
            PayRounding::Down => $truncated,
            PayRounding::Up => bccomp($remainder, '0', self::SCALE) > 0 ? bcadd($truncated, $step, $precision) : $truncated,
            PayRounding::HalfUp => bccomp(bcmul($remainder, '2', self::SCALE), $step, self::SCALE) >= 0 ? bcadd($truncated, $step, $precision) : $truncated,
        };

        return $negative && bccomp($rounded, '0', $precision) !== 0 ? '-'.$rounded : $rounded;
    }

    /**
     * rate % of base.
     */
    public static function percent(string $base, string $rate): string
    {
        return bcdiv(bcmul($base, $rate, self::SCALE), '100', self::SCALE);
    }

    public static function mul(string ...$factors): string
    {
        return array_reduce($factors, fn (string $product, string $factor): string => bcmul($product, $factor, self::SCALE), '1');
    }

    public static function div(string $dividend, string $divisor): string
    {
        return bccomp($divisor, '0', self::SCALE) === 0 ? '0' : bcdiv($dividend, $divisor, self::SCALE);
    }

    public static function min(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) <= 0 ? $a : $b;
    }

    public static function max(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) >= 0 ? $a : $b;
    }

    /**
     * Strip trailing zeros of a decimal string for display ("1.5000" → "1.5").
     */
    public static function trim(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
