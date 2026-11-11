<?php

namespace App\Enums;

/**
 * What a salary component does to pay: earnings add to gross, deductions reduce net,
 * employer contributions are an employer cost shown for information (never on net).
 */
enum PayComponentKind: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    case EmployerContribution = 'employer_contribution';

    public function label(): string
    {
        return __('payroll.enums.kinds.'.$this->value);
    }
}
