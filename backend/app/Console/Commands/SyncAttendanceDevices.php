<?php

namespace App\Console\Commands;

use App\Jobs\SyncAttendanceDevice;
use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pulls every active pull-mode device whose interval has elapsed (or whose
 * backoff after a failure is over). --now runs the pulls in this process.
 */
#[Signature('attendance:sync-devices {--now : Pull in this process instead of queueing jobs} {--device= : Only this device id}')]
#[Description('Pull attendance logs from devices that are due')]
class SyncAttendanceDevices extends Command
{
    public function handle(DeviceSyncService $sync): int
    {
        $devices = AttendanceDevice::query()->withoutGlobalScopes()->active()
            ->when($this->option('device'), fn ($query, $id) => $query->whereKey($id))
            ->get()
            ->filter(fn (AttendanceDevice $device): bool => $device->isDueForPull());

        foreach ($devices as $device) {
            if ($this->option('now')) {
                $log = $sync->pull($device);
                $this->line(sprintf('%s: %s (%d new, %d duplicates, %d unmatched)%s', $device->serial_number, $log->status->value, $log->created,
                    $log->duplicates, $log->unmatched, $log->is_simulated ? ' [SIMULATED]' : ''));
            } else {
                SyncAttendanceDevice::dispatch($device->id);
            }
        }

        $this->info($devices->count().' device(s) due.');

        return self::SUCCESS;
    }
}
