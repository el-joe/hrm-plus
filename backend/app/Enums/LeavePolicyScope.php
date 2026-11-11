<?php

namespace App\Enums;

/**
 * Who a leave policy applies to. Precedence: employee group > employment type > company.
 */
enum LeavePolicyScope: string
{
    case EmployeeGroup = 'employee_group';
    case EmploymentType = 'employment_type';
    case Company = 'company';

    public function label(): string
    {
        return __('leave.enums.policy_scopes.'.$this->value);
    }

    public function precedence(): int
    {
        return match ($this) {
            self::EmployeeGroup => 3,
            self::EmploymentType => 2,
            self::Company => 1,
        };
    }
}
