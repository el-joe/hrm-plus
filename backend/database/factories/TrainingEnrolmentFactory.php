<?php

namespace Database\Factories;

use App\Enums\TrainingEnrolmentSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\Employee;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An enrolled HR nomination (no approval run). Use the action in tests that exercise the rules.
 *
 * @extends Factory<TrainingEnrolment>
 */
class TrainingEnrolmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_session_id' => TrainingSession::factory(),
            'employee_id' => Employee::factory(),
            'source' => TrainingEnrolmentSource::Hr,
            'status' => TrainingEnrolmentStatus::Enrolled,
            'cost' => '1000.00',
        ];
    }
}
