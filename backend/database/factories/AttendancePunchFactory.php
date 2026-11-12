<?php

namespace Database\Factories;

use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A check-in derived from a raw device event.
 *
 * @extends Factory<AttendancePunch>
 */
class AttendancePunchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_raw_event_id' => AttendanceRawEvent::factory(),
            'employee_id' => fn (array $attributes) => AttendanceRawEvent::query()->withoutGlobalScopes()->findOrFail($attributes['attendance_raw_event_id'])->employee_id,
            'punched_at' => fn (array $attributes) => AttendanceRawEvent::query()->withoutGlobalScopes()->findOrFail($attributes['attendance_raw_event_id'])->occurred_at,
            'direction' => PunchDirection::In,
            'source' => RawEventSource::Device,
        ];
    }
}
