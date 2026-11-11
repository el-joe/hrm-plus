<?php

namespace App\Enums;

/**
 * A change to an active loan's schedule that needs approval.
 */
enum LoanChangeType: string
{
    case Skip = 'skip';
    case Reschedule = 'reschedule';

    public function label(): string
    {
        return __('payroll.enums.change_types.'.$this->value);
    }
}
