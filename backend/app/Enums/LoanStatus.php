<?php

namespace App\Enums;

/**
 * Life of an employee loan / advance. Active loans have an instalment schedule
 * and an outstanding balance; completed ones are fully repaid.
 */
enum LoanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('payroll.enums.loan_statuses.'.$this->value);
    }
}
