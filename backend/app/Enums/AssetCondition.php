<?php

namespace App\Enums;

/**
 * Physical condition of an asset, recorded on every handover and return.
 */
enum AssetCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
    case Damaged = 'damaged';

    public function label(): string
    {
        return __('assets.enums.conditions.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::New, self::Good => 'success',
            self::Fair => 'info',
            self::Poor => 'warning',
            self::Damaged => 'danger',
        };
    }
}
