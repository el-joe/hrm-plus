<?php

namespace App\Enums;

/**
 * draft → (pending_approval → approved | rejected) → sent → accepted | declined | expired;
 * withdrawn from any open state. App\Actions\Recruitment\ManageOffer enforces the flow.
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function label(): string
    {
        return __('recruitment.enums.offer_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingApproval => 'warning',
            self::Approved, self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected, self::Declined => 'danger',
            self::Withdrawn, self::Expired => 'neutral',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /**
     * Still in play (blocks a second offer on the same application).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved, self::Rejected, self::Sent], true);
    }
}
