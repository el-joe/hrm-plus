<?php

namespace App\Enums;

/**
 * Status of a processed attendance day (attendance_days.status).
 */
enum AttendanceDayStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Incomplete = 'incomplete';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case RestDay = 'rest_day';

    public function label(): string
    {
        return __('attendance.enums.day_statuses.'.$this->value);
    }

    /**
     * x-status-badge state.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Present => 'present',
            self::Late => 'late',
            self::Absent => 'absent',
            self::Incomplete => 'pending',
            self::OnLeave => 'on-leave',
            self::Holiday => 'finalized',
            self::RestDay => 'draft',
        };
    }

    /**
     * Whether the employee was expected and counted as attending.
     */
    public function isAttended(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
