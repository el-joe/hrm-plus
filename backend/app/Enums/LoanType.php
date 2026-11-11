<?php

namespace App\Enums;

/**
 * A loan (several instalments) or a salary advance (usually recovered from the next salary).
 */
enum LoanType: string
{
    case Loan = 'loan';
    case Advance = 'advance';

    public function label(): string
    {
        return __('payroll.enums.loan_types.'.$this->value);
    }
}
