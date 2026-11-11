<?php

namespace App\Enums;

/**
 * A break at fixed times, or a flexible break of a given duration taken any time.
 */
enum BreakType: string
{
    case Fixed = 'fixed';
    case Flexible = 'flexible';

    public function label(): string
    {
        return __('attendance.enums.break_types.'.$this->value);
    }
}
