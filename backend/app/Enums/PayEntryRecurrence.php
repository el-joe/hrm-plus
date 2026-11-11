<?php

namespace App\Enums;

/**
 * Recurring every period between its dates, once in the period containing its
 * date, or (deductions) a total split into monthly instalments.
 */
enum PayEntryRecurrence: string
{
    case Recurring = 'recurring';
    case OneTime = 'one_time';
    case Instalment = 'instalment';

    public function label(): string
    {
        return __('payroll.enums.recurrences.'.$this->value);
    }
}
