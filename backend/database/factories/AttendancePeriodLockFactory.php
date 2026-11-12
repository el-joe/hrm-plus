<?php

namespace Database\Factories;

use App\Models\AttendancePeriodLock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A company-wide lock of last month's attendance.
 *
 * @extends Factory<AttendancePeriodLock>
 */
class AttendancePeriodLockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'start_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'locked_at' => now(),
        ];
    }
}
