<?php

namespace App\Enums;

/**
 * Marital status (optional).
 */
enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return __('employees.enums.marital_statuses.'.$this->value);
    }
}
