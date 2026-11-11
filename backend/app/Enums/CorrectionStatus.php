<?php

namespace App\Enums;

/**
 * State of an attendance correction request.
 */
enum CorrectionStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('attendance.enums.correction_statuses.'.$this->value);
    }
}
