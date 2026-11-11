<?php

namespace App\Enums;

/**
 * How an offboarding case was opened.
 */
enum OffboardingSource: string
{
    case Manual = 'manual';
    case ContractExpiry = 'contract_expiry';

    public function label(): string
    {
        return __('onboarding.enums.offboarding_sources.'.$this->value);
    }
}
