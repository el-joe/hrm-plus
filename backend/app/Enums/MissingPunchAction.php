<?php

namespace App\Enums;

/**
 * What the engine does when a check-in or check-out is missing.
 */
enum MissingPunchAction: string
{
    case Incomplete = 'incomplete';
    case AssumeSchedule = 'assume_schedule';
    case Absent = 'absent';

    public function label(): string
    {
        return __('attendance.enums.missing_punch_actions.'.$this->value);
    }
}
