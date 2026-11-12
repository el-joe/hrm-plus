<?php

namespace Database\Factories;

use App\Enums\OnboardingStatus;
use App\Models\Employee;
use App\Models\Onboarding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An in-progress onboarding without a template or tasks.
 *
 * @extends Factory<Onboarding>
 */
class OnboardingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'start_date' => now()->toDateString(),
            'status' => OnboardingStatus::InProgress,
        ];
    }
}
