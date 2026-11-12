<?php

namespace Database\Factories;

use App\Enums\TrainingAttendanceStatus;
use App\Models\TrainingAttendance;
use App\Models\TrainingEnrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An enrolee present on a session day.
 *
 * @extends Factory<TrainingAttendance>
 */
class TrainingAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_enrolment_id' => TrainingEnrolment::factory(),
            'date' => now()->toDateString(),
            'status' => TrainingAttendanceStatus::Present,
        ];
    }
}
