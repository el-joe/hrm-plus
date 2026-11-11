<?php

namespace App\Enums;

/**
 * Pattern of a work schedule: by weekday, a rotation cycle of N days, or by day of the month.
 */
enum ScheduleType: string
{
    case Weekly = 'weekly';
    case Rotating = 'rotating';
    case Monthly = 'monthly';

    public function label(): string
    {
        return __('attendance.enums.schedule_types.'.$this->value);
    }
}
