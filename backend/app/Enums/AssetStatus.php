<?php

namespace App\Enums;

/**
 * Where an asset is in its life. Only "available" assets can be assigned;
 * "assigned" always has exactly one active asset_assignments row.
 */
enum AssetStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case InMaintenance = 'in_maintenance';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case Retired = 'retired';

    public function label(): string
    {
        return __('assets.enums.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Assigned => 'primary',
            self::InMaintenance => 'warning',
            self::Lost, self::Damaged => 'danger',
            self::Retired => 'neutral',
        };
    }
}
