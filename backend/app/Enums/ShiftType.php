<?php

namespace App\Enums;

/**
 * Kind of shift: fixed hours, or a flexible window with required minutes and optional core hours.
 */
enum ShiftType: string
{
    case Fixed = 'fixed';
    case Flexible = 'flexible';

    public function label(): string
    {
        return __('attendance.enums.shift_types.'.$this->value);
    }
}
