<?php

namespace App\Enums;

/**
 * What a manual attendance entry records for an employee and date.
 */
enum ManualEntryAction: string
{
    case Present = 'present';
    case Late = 'late';
    case CustomTimes = 'custom_times';
    case Status = 'status';

    public function label(): string
    {
        return __('attendance.enums.entry_actions.'.$this->value);
    }
}
