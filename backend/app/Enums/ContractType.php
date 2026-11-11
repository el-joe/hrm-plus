<?php

namespace App\Enums;

/**
 * Kind of employment contract.
 */
enum ContractType: string
{
    case Permanent = 'permanent';
    case FixedTerm = 'fixed_term';
    case Temporary = 'temporary';
    case Internship = 'internship';
    case Consultancy = 'consultancy';

    public function label(): string
    {
        return __('employees.enums.contract_types.'.$this->value);
    }

    /**
     * Fixed-term style contracts must have an end date.
     */
    public function requiresEndDate(): bool
    {
        return $this !== self::Permanent;
    }
}
