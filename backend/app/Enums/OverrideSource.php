<?php

namespace App\Enums;

/**
 * Why a date-specific schedule override exists.
 */
enum OverrideSource: string
{
    case Manual = 'manual';
    case ChangeRequest = 'change_request';
    case Swap = 'swap';

    public function label(): string
    {
        return __('attendance.enums.override_sources.'.$this->value);
    }
}
