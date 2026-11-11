<?php

namespace App\Enums;

/**
 * Kind of statutory rule table (rates are data, never code).
 */
enum StatutoryType: string
{
    case IncomeTax = 'income_tax';
    case SocialInsurance = 'social_insurance';

    public function label(): string
    {
        return __('payroll.enums.statutory_types.'.$this->value);
    }
}
