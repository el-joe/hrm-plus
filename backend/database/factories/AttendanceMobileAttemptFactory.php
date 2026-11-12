<?php

namespace Database\Factories;

use App\Enums\FaceVerificationMode;
use App\Enums\MobileAttemptOutcome;
use App\Enums\PunchDirection;
use App\Models\AttendanceMobileAttempt;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An accepted mobile check-in inside the geofence.
 *
 * @extends Factory<AttendanceMobileAttempt>
 */
class AttendanceMobileAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_uuid' => fake()->uuid(),
            'employee_id' => Employee::factory(),
            'direction' => PunchDirection::In,
            'outcome' => MobileAttemptOutcome::Accepted,
            'latitude' => '30.0444000',
            'longitude' => '31.2357000',
            'accuracy_meters' => '12.00',
            'within_geofence' => true,
            'server_time' => now(),
            'platform' => 'android',
            'work_date' => now()->toDateString(),
            'timezone' => 'Africa/Cairo',
            'face_mode' => FaceVerificationMode::Off,
        ];
    }
}
