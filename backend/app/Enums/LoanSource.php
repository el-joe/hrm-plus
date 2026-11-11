<?php

namespace App\Enums;

/**
 * Where a loan request was raised.
 */
enum LoanSource: string
{
    case Admin = 'admin';
    case Portal = 'portal';
    case Api = 'api';

    public function label(): string
    {
        return __('payroll.enums.sources.'.$this->value);
    }
}
