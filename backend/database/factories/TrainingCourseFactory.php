<?php

namespace Database\Factories;

use App\Enums\TrainingDeliveryMode;
use App\Models\TrainingCourse;
use App\Models\TrainingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A classroom course of 8 hours costing 1000.00 per participant, without a certificate.
 *
 * @extends Factory<TrainingCourse>
 */
class TrainingCourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'TC'.$number,
            'training_provider_id' => TrainingProvider::factory(),
            'name_ar' => 'دورة '.$number,
            'name_en' => 'Course '.$number,
            'delivery_mode' => TrainingDeliveryMode::Classroom,
            'duration_hours' => '8.00',
            'cost_per_participant' => '1000.00',
            'issues_certificate' => false,
            'is_active' => true,
        ];
    }

    /**
     * Issues a certificate valid for the given months (null = no expiry).
     */
    public function withCertificate(?int $months = 24): static
    {
        return $this->state(fn (array $attributes) => ['issues_certificate' => true, 'certificate_validity_months' => $months]);
    }
}
