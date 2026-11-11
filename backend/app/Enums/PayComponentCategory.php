<?php

namespace App\Enums;

/**
 * Grouping of salary components on payslips and reports.
 */
enum PayComponentCategory: string
{
    case Basic = 'basic';
    case Allowance = 'allowance';
    case Bonus = 'bonus';
    case Commission = 'commission';
    case Overtime = 'overtime';
    case Attendance = 'attendance';
    case Leave = 'leave';
    case Loan = 'loan';
    case Advance = 'advance';
    case Insurance = 'insurance';
    case Tax = 'tax';
    case Penalty = 'penalty';
    case Other = 'other';

    public function label(): string
    {
        return __('payroll.enums.categories.'.$this->value);
    }
}
