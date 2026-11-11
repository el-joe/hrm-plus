<?php

namespace App\Enums;

/**
 * State of a request that goes through the approval engine.
 */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('attendance.enums.request_statuses.'.$this->value);
    }
}
