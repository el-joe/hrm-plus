<?php

namespace App\Enums;

/**
 * Direction of a punch; unknown punches are paired alternately.
 */
enum PunchDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Unknown = 'unknown';

    public function label(): string
    {
        return __('attendance.enums.directions.'.$this->value);
    }
}
