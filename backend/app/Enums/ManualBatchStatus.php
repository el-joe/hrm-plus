<?php

namespace App\Enums;

/**
 * State of a bulk manual-attendance batch or an attendance import.
 */
enum ManualBatchStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('attendance.enums.batch_statuses.'.$this->value);
    }
}
