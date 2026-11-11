<?php

namespace App\Enums;

/**
 * Whether unpaid break time is deducted as scheduled or taken from the actual gaps between punches.
 */
enum BreakMode: string
{
    case Scheduled = 'scheduled';
    case Actual = 'actual';

    public function label(): string
    {
        return __('attendance.enums.break_modes.'.$this->value);
    }
}
