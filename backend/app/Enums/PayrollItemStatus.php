<?php

namespace App\Enums;

/**
 * Calculation state of one employee in a run.
 */
enum PayrollItemStatus: string
{
    case Pending = 'pending';
    case Calculated = 'calculated';
    case Failed = 'failed';

    public function label(): string
    {
        return __('payroll_runs.enums.item_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Calculated => 'success',
            self::Failed => 'danger',
        };
    }
}
