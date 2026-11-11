<?php

namespace App\Enums;

/**
 * What produced a device sync log row.
 */
enum DeviceSyncType: string
{
    case Pull = 'pull';
    case Push = 'push';
    case Historical = 'historical';
    case File = 'file';
    case Test = 'test';

    public function label(): string
    {
        return __('devices.enums.sync_types.'.$this->value);
    }
}
