<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b attendance report "overtime" (AttendanceReports).
 */
class OvertimeReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'overtime';
    }

    protected function legacyKey(): string
    {
        return 'overtime';
    }
}
