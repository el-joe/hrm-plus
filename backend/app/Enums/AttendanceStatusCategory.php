<?php

namespace App\Enums;

/**
 * What a configurable attendance status means to the engine.
 */
enum AttendanceStatusCategory: string
{
    case Present = 'present';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case RestDay = 'rest_day';

    public function label(): string
    {
        return __('attendance.enums.status_categories.'.$this->value);
    }
}
