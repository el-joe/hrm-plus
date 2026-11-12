<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b attendance report "absence" (AttendanceReports).
 */
class AbsenceReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'absence';
    }

    protected function legacyKey(): string
    {
        return 'absence';
    }
}
