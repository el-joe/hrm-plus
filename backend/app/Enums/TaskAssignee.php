<?php

namespace App\Enums;

/**
 * Who is responsible for an onboarding task or an offboarding handover item.
 */
enum TaskAssignee: string
{
    case Hr = 'hr';
    case It = 'it';
    case Manager = 'manager';
    case Finance = 'finance';
    case Employee = 'employee';

    public function label(): string
    {
        return __('onboarding.enums.assignees.'.$this->value);
    }
}
