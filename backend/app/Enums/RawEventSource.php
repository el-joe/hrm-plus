<?php

namespace App\Enums;

/**
 * Where an attendance input came from. Every source goes through the same engine.
 */
enum RawEventSource: string
{
    case Device = 'device';
    case MobileGps = 'mobile_gps';
    case Manual = 'manual';
    case Import = 'import';

    public function label(): string
    {
        return __('attendance.enums.sources.'.$this->value);
    }
}
