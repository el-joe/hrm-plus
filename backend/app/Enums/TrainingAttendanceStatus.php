<?php

namespace App\Enums;

/**
 * Attendance of a participant on one session day. Present and late count as attended.
 */
enum TrainingAttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return __('training.enums.attendance_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Excused => 'neutral',
        };
    }

    public function attended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
