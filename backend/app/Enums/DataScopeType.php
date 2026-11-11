<?php

namespace App\Enums;

/**
 * The dimensions an admin's data access can be restricted by.
 */
enum DataScopeType: string
{
    case Branch = 'branch';
    case Department = 'department';
    case EmployeeGroup = 'employee_group';

    public function label(): string
    {
        return __('app.data_scope_types.'.$this->value);
    }
}
