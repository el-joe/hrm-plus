<?php

namespace App\Enums;

/**
 * A payroll period: a calendar month (from setting payroll.period_start_day) or custom dates.
 */
enum PayrollPeriodType: string
{
    case Monthly = 'monthly';
    case Custom = 'custom';

    public function label(): string
    {
        return __('payroll_runs.enums.period_types.'.$this->value);
    }
}
