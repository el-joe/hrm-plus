<?php

namespace App\Enums;

/**
 * A raw event is either a punch (an instant) or a day mark (a status for a whole day).
 */
enum RawEventKind: string
{
    case Punch = 'punch';
    case DayMark = 'day_mark';

    public function label(): string
    {
        return __('attendance.enums.event_kinds.'.$this->value);
    }
}
