<?php

namespace App\Enums;

/**
 * How a device delivers its logs: pushes them, is polled, or only exports files.
 */
enum DeviceConnectionMode: string
{
    case Push = 'push';
    case Pull = 'pull';
    case File = 'file';

    public function label(): string
    {
        return __('devices.enums.connection_modes.'.$this->value);
    }
}
