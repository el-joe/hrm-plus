<?php

namespace App\Enums;

/**
 * State of one scheduled loan instalment.
 */
enum LoanInstalmentStatus: string
{
    case Scheduled = 'scheduled';
    case Deducted = 'deducted';
    case Repaid = 'repaid';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('payroll.enums.instalment_statuses.'.$this->value);
    }
}
