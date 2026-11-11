<?php

namespace App\Enums;

/**
 * A payslip is issued when its run is finalised and voided when the run is reversed.
 */
enum PayslipStatus: string
{
    case Issued = 'issued';
    case Void = 'void';

    public function label(): string
    {
        return __('payroll_runs.enums.payslip_statuses.'.$this->value);
    }
}
