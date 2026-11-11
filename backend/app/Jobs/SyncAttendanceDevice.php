<?php

namespace App\Jobs;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scheduled pull of one device. Failures are not retried by the queue: the
 * sync service logs them and schedules the next attempt with backoff
 * (next_retry_at), which attendance:sync-devices honours.
 */
class SyncAttendanceDevice implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public int $deviceId) {}

    public function uniqueId(): string
    {
        return (string) $this->deviceId;
    }

    public function handle(DeviceSyncService $sync): void
    {
        $device = AttendanceDevice::query()->withoutGlobalScopes()->find($this->deviceId);

        if ($device !== null && $device->isDueForPull()) {
            $sync->pull($device);
        }
    }
}
