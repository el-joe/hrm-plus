<?php

namespace Database\Factories;

use App\Enums\DeviceSyncStatus;
use App\Enums\DeviceSyncType;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A successful pull from a device.
 *
 * @extends Factory<AttendanceDeviceSyncLog>
 */
class AttendanceDeviceSyncLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_device_id' => AttendanceDevice::factory(),
            'type' => DeviceSyncType::Pull,
            'status' => DeviceSyncStatus::Success,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
