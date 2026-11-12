<?php

namespace Database\Factories;

use App\Models\AttendanceDeviceUser;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Maps a device user id (the same on every device) to an employee.
 *
 * @extends Factory<AttendanceDeviceUser>
 */
class AttendanceDeviceUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_device_id' => null,
            'device_scope' => 0,
            'device_user_id' => (string) fake()->unique()->numberBetween(1, 9_999_999),
            'employee_id' => Employee::factory(),
        ];
    }
}
