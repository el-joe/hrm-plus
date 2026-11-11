<?php

namespace App\Enums;

/**
 * Health of an attendance device, derived from its sync state (AttendanceDevice::health()).
 */
enum DeviceHealth: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';
    case Unknown = 'unknown';
    case Disabled = 'disabled';

    public function label(): string
    {
        return __('devices.enums.health.'.$this->value);
    }

    /**
     * x-badge tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Warning => 'warning',
            self::Error => 'danger',
            self::Unknown, self::Disabled => 'neutral',
        };
    }
}
