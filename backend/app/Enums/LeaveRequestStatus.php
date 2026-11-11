<?php

namespace App\Enums;

/**
 * State of a leave request. cancellation_pending: approved leave whose
 * cancellation waits for approval; it still counts as approved meanwhile.
 */
enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case CancellationPending = 'cancellation_pending';

    public function label(): string
    {
        return __('leave.enums.request_statuses.'.$this->value);
    }

    /**
     * x-status-badge state.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Pending, self::CancellationPending => 'pending',
            self::Approved => 'approved',
            self::Rejected => 'rejected',
            self::Cancelled => 'draft',
        };
    }

    /**
     * Statuses that block the dates (overlap check, calendar).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Approved, self::CancellationPending];
    }

    /**
     * Statuses of leave that is taken (attendance, balance usage, payroll).
     *
     * @return list<self>
     */
    public static function taken(): array
    {
        return [self::Approved, self::CancellationPending];
    }

    public function isTaken(): bool
    {
        return in_array($this, self::taken(), true);
    }
}
