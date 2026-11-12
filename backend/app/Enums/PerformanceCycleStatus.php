<?php

namespace App\Enums;

/**
 * Draft cycles can be edited; launching creates the reviews; a closed cycle is read-only.
 */
enum PerformanceCycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return __('performance.enums.cycle_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'info',
            self::Closed => 'success',
        };
    }
}
