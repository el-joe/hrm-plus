<?php

namespace App\Enums;

/**
 * Why a post-finalisation adjustment exists: attendance changed after the
 * period was paid (locked-day adjustments), leave changed (e.g. a
 * cancellation approved after finalisation), or an administrator entered it.
 */
enum PayrollAdjustmentSource: string
{
    case Attendance = 'attendance';
    case Leave = 'leave';
    case Manual = 'manual';

    public function label(): string
    {
        return __('payroll_runs.enums.adjustment_sources.'.$this->value);
    }
}
