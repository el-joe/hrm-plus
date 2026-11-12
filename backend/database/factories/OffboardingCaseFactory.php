<?php

namespace Database\Factories;

use App\Enums\OffboardingSource;
use App\Enums\OffboardingStatus;
use App\Enums\TerminationType;
use App\Models\Employee;
use App\Models\OffboardingCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open resignation case with a last working day next month.
 *
 * @extends Factory<OffboardingCase>
 */
class OffboardingCaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => TerminationType::Resignation,
            'source' => OffboardingSource::Manual,
            'notice_date' => now()->toDateString(),
            'last_working_date' => now()->addMonthNoOverflow()->toDateString(),
            'reason' => fake()->sentence(),
            'eligible_for_rehire' => true,
            'status' => OffboardingStatus::Open,
        ];
    }
}
