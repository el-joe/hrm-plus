<?php

namespace App\Enums;

/**
 * Outcome of one device sync run.
 */
enum DeviceSyncStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return __('devices.enums.sync_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Success => 'success',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }
}
