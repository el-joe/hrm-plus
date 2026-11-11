<?php

namespace App\Enums;

/**
 * What happened in one entry of an approval request's history.
 */
enum ApprovalActionType: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.approval_actions.'.$this->value);
    }
}
