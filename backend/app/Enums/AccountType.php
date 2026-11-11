<?php

namespace App\Enums;

/**
 * The system has exactly two account types. Administrative roles (HR Manager,
 * Recruiter, ...) are RBAC roles inside the Admin type, never extra types.
 */
enum AccountType: string
{
    case Admin = 'admin';
    case Employee = 'employee';

    public function label(): string
    {
        return __('app.account_types.'.$this->value);
    }
}
