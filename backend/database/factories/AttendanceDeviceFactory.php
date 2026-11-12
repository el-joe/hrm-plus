<?php

namespace Database\Factories;

use App\Enums\DeviceConnectionMode;
use App\Models\AttendanceDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active simulated device polled every 15 minutes.
 *
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Device '.fake()->unique()->numberBetween(1, 99999),
            'serial_number' => 'SN'.fake()->unique()->numerify('##########'),
            'adapter' => 'simulated',
            'connection_mode' => DeviceConnectionMode::Pull,
            'timezone' => 'Africa/Cairo',
            'pull_interval_minutes' => 15,
            'is_active' => true,
        ];
    }
}
