<?php

namespace Database\Factories;

use App\Enums\FaceVerificationResult;
use App\Enums\PunchDirection;
use App\Models\FaceEnrolment;
use App\Models\FaceVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A matched check-in verification with the simulated provider.
 *
 * @extends Factory<FaceVerification>
 */
class FaceVerificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'face_enrolment_id' => FaceEnrolment::factory(),
            'employee_id' => fn (array $attributes) => FaceEnrolment::query()->findOrFail($attributes['face_enrolment_id'])->employee_id,
            'provider' => 'simulated',
            'is_simulated' => true,
            'direction' => PunchDirection::In,
            'result' => FaceVerificationResult::Matched,
            'score' => '0.9300',
            'liveness_passed' => true,
            'work_date' => now()->toDateString(),
            'verified_at' => now(),
        ];
    }
}
