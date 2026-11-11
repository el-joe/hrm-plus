<?php

namespace App\Enums;

/**
 * Where a payroll line comes from (shown with the line on the breakdown screen).
 */
enum PayrollLineSource: string
{
    case Basic = 'basic';
    case Structure = 'structure';
    case Entry = 'entry';
    case Attendance = 'attendance';
    case Leave = 'leave';
    case Encashment = 'encashment';
    case Loan = 'loan';
    case Statutory = 'statutory';
    case Correction = 'correction';
    case Reversal = 'reversal';

    public function label(): string
    {
        return __('payroll_runs.enums.line_sources.'.$this->value);
    }
}
