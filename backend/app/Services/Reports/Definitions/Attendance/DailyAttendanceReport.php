<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b attendance report "daily" (AttendanceReports).
 */
class DailyAttendanceReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'daily_attendance';
    }

    protected function legacyKey(): string
    {
        return 'daily';
    }
}
