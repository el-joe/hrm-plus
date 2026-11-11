<?php

namespace App\Enums;

/**
 * Approval state of the overtime of a processed day.
 */
enum OvertimeStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('attendance.enums.overtime_statuses.'.$this->value);
    }
}
