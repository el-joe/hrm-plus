<?php

namespace App\Enums;

/**
 * Payment state of a payslip, derived from its recorded payments.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return __('payroll_runs.enums.payment_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
        };
    }
}
