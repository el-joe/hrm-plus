<?php

namespace App\Enums;

/**
 * How an employee's basic salary (employee_salaries.basic_salary) is expressed:
 * monthly salary, daily rate, hourly rate, or a fixed amount per period that
 * is never prorated or reduced by attendance.
 */
enum PayBasis: string
{
    case Monthly = 'monthly';
    case Daily = 'daily';
    case Hourly = 'hourly';
    case Fixed = 'fixed';

    public function label(): string
    {
        return __('payroll.enums.pay_bases.'.$this->value);
    }
}
