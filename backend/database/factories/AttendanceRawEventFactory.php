<?php

namespace Database\Factories;

use App\Enums\PunchDirection;
use App\Enums\RawEventKind;
use App\Enums\RawEventSource;
use App\Enums\RawEventStatus;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending (unprocessed) device check-in. Tests that need processing should use
 * App\Services\Attendance\RawEventRecorder.
 *
 * @extends Factory<AttendanceRawEvent>
 */
class AttendanceRawEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => RawEventKind::Punch,
            'source' => RawEventSource::Device,
            'employee_id' => Employee::factory(),
            'occurred_at' => now()->subDay()->setTime(8, 0),
            'timezone' => 'Africa/Cairo',
            'direction' => PunchDirection::In,
            'idempotency_key' => 'factory:'.fake()->uuid(),
            'status' => RawEventStatus::Pending,
        ];
    }
}
