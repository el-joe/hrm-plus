<?php

namespace App\Enums;

/**
 * How employment ended.
 */
enum TerminationType: string
{
    case Resignation = 'resignation';
    case Dismissal = 'dismissal';
    case ContractEnd = 'contract_end';
    case Retirement = 'retirement';
    case MutualAgreement = 'mutual_agreement';
    case Other = 'other';

    public function label(): string
    {
        return __('employees.enums.termination_types.'.$this->value);
    }
}
