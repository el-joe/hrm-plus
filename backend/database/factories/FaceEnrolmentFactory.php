<?php

namespace Database\Factories;

use App\Enums\FaceEnrolmentStatus;
use App\Models\FaceConsent;
use App\Models\FaceEnrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active enrolment with the simulated provider.
 *
 * @extends Factory<FaceEnrolment>
 */
class FaceEnrolmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'face_consent_id' => FaceConsent::factory(),
            'employee_id' => fn (array $attributes) => FaceConsent::query()->findOrFail($attributes['face_consent_id'])->employee_id,
            'provider' => 'simulated',
            'is_simulated' => true,
            'status' => FaceEnrolmentStatus::Active,
            'quality_score' => '0.9500',
            'enrolled_at' => now(),
        ];
    }
}
