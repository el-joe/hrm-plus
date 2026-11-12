<?php

namespace App\Enums;

/**
 * Entries of the asset history (asset_events).
 */
enum AssetEventType: string
{
    case Registered = 'registered';
    case Updated = 'updated';
    case Assigned = 'assigned';
    case Acknowledged = 'acknowledged';
    case Returned = 'returned';
    case MaintenanceStarted = 'maintenance_started';
    case MaintenanceCompleted = 'maintenance_completed';
    case MarkedLost = 'marked_lost';
    case MarkedDamaged = 'marked_damaged';
    case Retired = 'retired';

    public function label(): string
    {
        return __('assets.enums.event_types.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Assigned, self::Acknowledged => 'primary',
            self::Returned, self::MaintenanceCompleted, self::Registered => 'success',
            self::MaintenanceStarted => 'warning',
            self::MarkedLost, self::MarkedDamaged => 'danger',
            self::Retired, self::Updated => 'neutral',
        };
    }
}
