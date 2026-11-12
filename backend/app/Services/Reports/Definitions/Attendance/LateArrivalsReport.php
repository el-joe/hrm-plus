<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b attendance report "lateness" (AttendanceReports).
 */
class LateArrivalsReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'late_arrivals';
    }

    protected function legacyKey(): string
    {
        return 'lateness';
    }
}
