<?php

namespace App\Enums;

/**
 * State of a proposed disciplinary action in the "disciplinary_action" approval workflow.
 */
enum DisciplinaryActionStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('disciplinary.enums.action_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingApproval => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }
}
