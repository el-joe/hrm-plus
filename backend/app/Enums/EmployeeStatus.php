<?php

namespace App\Enums;

/**
 * Employment status of an employee. Terminated employees keep every record; rehiring makes them active again.
 */
enum EmployeeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Terminated = 'terminated';

    public function label(): string
    {
        return __('employees.enums.statuses.'.$this->value);
    }

    /**
     * x-badge tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Terminated => 'danger',
        };
    }
}
