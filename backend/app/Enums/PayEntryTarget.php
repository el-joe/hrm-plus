<?php

namespace App\Enums;

/**
 * Who an allowance / deduction entry applies to. Precedence per component,
 * most specific first: employee > employee group > department (nearest up the tree) > branch > company.
 */
enum PayEntryTarget: string
{
    case Employee = 'employee';
    case EmployeeGroup = 'employee_group';
    case Department = 'department';
    case Branch = 'branch';
    case Company = 'company';

    public function label(): string
    {
        return __('payroll.enums.entry_targets.'.$this->value);
    }
}
