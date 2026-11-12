<?php

namespace Database\Factories;

use App\Enums\TaskAssignee;
use App\Enums\TaskStatus;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending HR task of an onboarding, due in a week.
 *
 * @extends Factory<OnboardingTask>
 */
class OnboardingTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_id' => Onboarding::factory(),
            'title_ar' => 'مهمة',
            'title_en' => ucfirst(fake()->words(3, true)),
            'assignee' => TaskAssignee::Hr,
            'due_date' => now()->addWeek()->toDateString(),
            'status' => TaskStatus::Pending,
        ];
    }
}
