<?php

namespace App\Enums;

/**
 * State of one custody period: active (with the employee), returned, or lost while assigned.
 */
enum AssetAssignmentStatus: string
{
    case Active = 'active';
    case Returned = 'returned';
    case Lost = 'lost';

    public function label(): string
    {
        return __('assets.enums.assignment_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::Returned => 'success',
            self::Lost => 'danger',
        };
    }
}
