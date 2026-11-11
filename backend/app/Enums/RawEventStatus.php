<?php

namespace App\Enums;

/**
 * Processing state of a raw attendance event.
 */
enum RawEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Unmatched = 'unmatched';
    case Void = 'void';

    public function label(): string
    {
        return __('attendance.enums.event_statuses.'.$this->value);
    }
}
