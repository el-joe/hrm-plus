<?php

namespace App\Enums;

/**
 * Smallest unit an employee may request for a leave type.
 */
enum LeaveUnit: string
{
    case Day = 'day';
    case HalfDay = 'half_day';
    case Hour = 'hour';

    public function label(): string
    {
        return __('leave.enums.units.'.$this->value);
    }

    public function allows(LeaveDayPart $part): bool
    {
        return match ($part) {
            LeaveDayPart::Full => true,
            LeaveDayPart::FirstHalf, LeaveDayPart::SecondHalf => $this !== self::Day,
            LeaveDayPart::Hours => $this === self::Hour,
        };
    }
}
