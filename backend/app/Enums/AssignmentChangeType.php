<?php

namespace App\Enums;

/**
 * Why an employee_assignments row (an effective-dated snapshot of the job placement) was written.
 */
enum AssignmentChangeType: string
{
    case Hire = 'hire';
    case Transfer = 'transfer';
    case Promotion = 'promotion';
    case Reassignment = 'reassignment';
    case ManagerChange = 'manager_change';
    case StatusChange = 'status_change';
    case Termination = 'termination';
    case Rehire = 'rehire';

    public function label(): string
    {
        return __('employees.enums.change_types.'.$this->value);
    }
}
