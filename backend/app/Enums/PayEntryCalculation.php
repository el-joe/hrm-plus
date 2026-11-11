<?php

namespace App\Enums;

/**
 * Amount of an allowance / deduction entry: a fixed amount, or a percentage of
 * the basic salary or of another component.
 */
enum PayEntryCalculation: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return __('payroll.enums.entry_calculations.'.$this->value);
    }
}
