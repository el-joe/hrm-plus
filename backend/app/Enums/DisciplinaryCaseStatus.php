<?php

namespace App\Enums;

/**
 * Life of a disciplinary case:
 * reported → investigating → pending_approval (an action was proposed) → action_issued → closed,
 * or dismissed (no action). A rejected action sends the case back to investigating.
 */
enum DisciplinaryCaseStatus: string
{
    case Reported = 'reported';
    case Investigating = 'investigating';
    case PendingApproval = 'pending_approval';
    case ActionIssued = 'action_issued';
    case Closed = 'closed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('disciplinary.enums.case_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reported => 'info',
            self::Investigating, self::PendingApproval => 'warning',
            self::ActionIssued => 'danger',
            self::Closed => 'success',
            self::Dismissed => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Dismissed], true);
    }

    /**
     * An action may be proposed (and the case dismissed) only before a decision is pending or issued.
     */
    public function acceptsProposal(): bool
    {
        return in_array($this, [self::Reported, self::Investigating], true);
    }
}
