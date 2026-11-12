<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b "manual_entries" report: manual and imported attendance events.
 */
class ManualEntriesReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'manual_attendance_entries';
    }

    protected function legacyKey(): string
    {
        return 'manual_entries';
    }

    protected function employeeColumn(): ?string
    {
        return 'attendance_raw_events.employee_id';
    }
}
