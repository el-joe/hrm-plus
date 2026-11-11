<?php

namespace App\Enums;

/**
 * Life of a payroll run:
 * draft → calculated (review; recalculate any number of times) → pending_approval (approval engine)
 * → approved → finalised (locked; payslips issued, loans deducted) → reversed (only through a reversal).
 * "calculating" while a queued calculation runs; a rejected approval returns the run to "calculated".
 * Draft / calculated runs can be cancelled (their pay slots are released).
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculating = 'calculating';
    case Calculated = 'calculated';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Finalised = 'finalised';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('payroll_runs.enums.run_statuses.'.$this->value);
    }

    /**
     * x-status-badge state.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft, self::Calculating => 'draft',
            self::Calculated, self::PendingApproval => 'pending',
            self::Approved => 'approved',
            self::Finalised => 'finalized',
            self::Reversed, self::Cancelled => 'rejected',
        };
    }

    /**
     * The population and amounts may still change.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    /**
     * Finalised data: never updated again.
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::Finalised, self::Reversed], true);
    }

    /**
     * Holds its pay slots (cancelled and reversed runs released them).
     */
    public function isLive(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Reversed], true);
    }

    /**
     * Steps of the wizard progress bar, in order.
     *
     * @return list<self>
     */
    public static function steps(): array
    {
        return [self::Draft, self::Calculated, self::PendingApproval, self::Approved, self::Finalised];
    }
}
