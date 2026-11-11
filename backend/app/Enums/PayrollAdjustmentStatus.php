<?php

namespace App\Enums;

/**
 * pending: waits for a run in a later period (or a correction run);
 * applied: paid / recovered by a finalised run; dismissed: rejected with a reason.
 */
enum PayrollAdjustmentStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('payroll_runs.enums.adjustment_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Applied => 'success',
            self::Dismissed => 'neutral',
        };
    }
}
