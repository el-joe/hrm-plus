<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b attendance report "monthly_summary" (AttendanceReports).
 */
class MonthlyAttendanceReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'monthly_attendance';
    }

    protected function legacyKey(): string
    {
        return 'monthly_summary';
    }
}
