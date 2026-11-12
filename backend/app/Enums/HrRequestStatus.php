<?php

namespace App\Enums;

/**
 * Life of an HR request (Phase 8b):
 *
 *   pending_approval → open (approved) | rejected
 *   open → in_progress ⇄ awaiting_employee → resolved | rejected
 *   pending_approval / open → cancelled (by the employee or HR)
 */
enum HrRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case Open = 'open';
    case InProgress = 'in_progress';
    case AwaitingEmployee = 'awaiting_employee';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('hr_requests.enums.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingApproval => 'warning',
            self::Open => 'info',
            self::InProgress => 'primary',
            self::AwaitingEmployee => 'warning',
            self::Resolved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Rejected, self::Cancelled], true);
    }

    /**
     * HR is working on it (thread, assignment, letters, profile changes).
     */
    public function isHandled(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::AwaitingEmployee], true);
    }

    /**
     * Counts toward the SLA (the employee is not the one holding it up).
     */
    public function isEscalatable(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    /**
     * The employee may still withdraw it (nobody started on it).
     */
    public function isCancellableByEmployee(): bool
    {
        return in_array($this, [self::PendingApproval, self::Open], true);
    }

    /**
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return [self::PendingApproval, self::Open, self::InProgress, self::AwaitingEmployee];
    }
}
