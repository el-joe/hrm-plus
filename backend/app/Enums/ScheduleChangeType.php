<?php

namespace App\Enums;

/**
 * A schedule change for one employee, or a swap of two employees' shifts on a date.
 */
enum ScheduleChangeType: string
{
    case Change = 'change';
    case Swap = 'swap';

    public function label(): string
    {
        return __('attendance.enums.schedule_change_types.'.$this->value);
    }
}
