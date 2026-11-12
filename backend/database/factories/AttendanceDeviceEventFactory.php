<?php

namespace Database\Factories;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceEvent;
use App\Models\AttendanceRawEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Device metadata of a punch received from an attendance device.
 *
 * @extends Factory<AttendanceDeviceEvent>
 */
class AttendanceDeviceEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_raw_event_id' => AttendanceRawEvent::factory(),
            'attendance_device_id' => AttendanceDevice::factory(),
            'device_user_id' => (string) fake()->numberBetween(1, 9999),
        ];
    }
}
