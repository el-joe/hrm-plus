<?php

namespace Database\Factories;

use App\Enums\ManualEntryAction;
use App\Models\AttendanceManualBatch;
use App\Models\AttendanceManualEntry;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Marks an employee present in a manual batch.
 *
 * @extends Factory<AttendanceManualEntry>
 */
class AttendanceManualEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_manual_batch_id' => AttendanceManualBatch::factory(),
            'employee_id' => Employee::factory(),
            'work_date' => now()->subDay()->toDateString(),
            'action' => ManualEntryAction::Present,
        ];
    }
}
