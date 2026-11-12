<?php

namespace App\Services\Reports\Definitions\Attendance;

/**
 * Reuses the Phase 5b "device_sync" report: device synchronisation log (needs attendance_devices.view,
 * as the attendance reports screen).
 */
class DeviceSynchronizationReport extends LegacyAttendanceReport
{
    public function key(): string
    {
        return 'device_synchronization';
    }

    protected function legacyKey(): string
    {
        return 'device_sync';
    }

    protected function employeeColumn(): ?string
    {
        return null;
    }

    public function requires(): array
    {
        return ['attendance_devices.view'];
    }

    public function filters(): array
    {
        return ['date_range', 'branch'];
    }
}
