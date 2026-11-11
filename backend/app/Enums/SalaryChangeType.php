<?php

namespace App\Enums;

/**
 * Why an employee_salaries row (an effective-dated basic salary) was written.
 */
enum SalaryChangeType: string
{
    case Initial = 'initial';
    case Increase = 'increase';
    case Decrease = 'decrease';
    case Promotion = 'promotion';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('employees.enums.salary_change_types.'.$this->value);
    }
}
