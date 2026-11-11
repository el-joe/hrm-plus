<?php

namespace App\Enums;

/**
 * Which part of the day a request covers. Partial parts are single-day requests.
 */
enum LeaveDayPart: string
{
    case Full = 'full';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';
    case Hours = 'hours';

    public function label(): string
    {
        return __('leave.enums.day_parts.'.$this->value);
    }

    public function isPartial(): bool
    {
        return $this !== self::Full;
    }

    public function isHalf(): bool
    {
        return $this === self::FirstHalf || $this === self::SecondHalf;
    }
}
