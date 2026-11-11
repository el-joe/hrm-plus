<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Payroll configuration that cannot be calculated: a percentage chain that
 * loops, or a percentage based on a component that is not part of the
 * calculation. Saving components and structures refuses these up front.
 */
class PayrollConfigurationException extends RuntimeException
{
    /**
     * @param  list<string>  $codes
     */
    public static function percentageCycle(array $codes): self
    {
        return new self(__('payroll.validation.percentage_cycle', ['codes' => implode(', ', $codes)]));
    }

    public static function missingBase(string $code, string $base): self
    {
        return new self(__('payroll.validation.missing_base', ['code' => $code, 'base' => $base]));
    }
}
