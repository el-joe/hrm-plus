<?php

use App\Services\Attendance\Devices\Adapters\SimulatedDeviceAdapter;

/*
|--------------------------------------------------------------------------
| Attendance device adapters (Phase 5b)
|--------------------------------------------------------------------------
|
| Each adapter implements App\Services\Attendance\Devices\Contracts\AttendanceDeviceAdapter.
| No vendor brand is listed as supported: the fingerprint device model is
| not known yet (PROJECT_PLAN.md, blocker B3). Add a vendor adapter here only
| after it was tested against the real device and firmware — see
| docs/ATTENDANCE_DEVICES.md. "simulated" generates plausible logs for
| development and is labelled "Simulated" in every screen and log.
|
*/

return [

    'adapters' => [
        'simulated' => [
            'class' => SimulatedDeviceAdapter::class,
            'simulated' => true,
            'modes' => ['push', 'pull', 'file'],
        ],
    ],

    // Days a single pull may cover (older logs: use the historical import).
    'max_pull_days' => 7,

    // Longest historical import range in days.
    'max_historical_days' => 62,

    // Rows of one CSV / Excel device-log file.
    'import_max_rows' => 20000,

    // Events accepted in one push request.
    'push_max_events' => 500,

    // Push requests per device per minute.
    'push_per_minute' => 120,

    // Backoff after a failed pull: minutes = min(base * 2^(failures - 1), max).
    'retry_base_minutes' => 5,
    'retry_max_minutes' => 360,

];
