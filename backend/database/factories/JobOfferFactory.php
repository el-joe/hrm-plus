<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\JobApplication;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft offer of 8000 a month starting next month.
 *
 * @extends Factory<JobOffer>
 */
class JobOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'OF-'.str_pad((string) fake()->unique()->numberBetween(1, 9_999_999), 7, '0', STR_PAD_LEFT),
            'job_application_id' => JobApplication::factory(),
            'basic_salary' => '8000.00',
            'currency' => 'USD',
            'start_date' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            'expires_on' => now()->addWeeks(2)->toDateString(),
            'probation_months' => 3,
            'status' => OfferStatus::Draft,
        ];
    }
}
