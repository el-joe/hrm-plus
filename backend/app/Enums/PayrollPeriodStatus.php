<?php

namespace App\Enums;

/**
 * Open periods accept new runs; a closed period accepts none (reopen to add one).
 */
enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('payroll_runs.enums.period_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return $this === self::Open ? 'success' : 'neutral';
    }
}
