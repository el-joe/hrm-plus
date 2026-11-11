<?php

namespace App\Enums;

/**
 * How a payslip amount was paid.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';

    public function label(): string
    {
        return __('payroll_runs.enums.payment_methods.'.$this->value);
    }
}
