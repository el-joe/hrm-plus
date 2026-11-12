<?php

namespace Database\Factories;

use App\Enums\CorrectionSource;
use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending correction of yesterday's check-in and check-out filed by HR.
 *
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->setTime(8, 0),
            'check_out_at' => now()->subDay()->setTime(16, 0),
            'reason' => fake()->sentence(),
            'source' => CorrectionSource::Admin,
            'status' => CorrectionStatus::Pending,
        ];
    }
}
