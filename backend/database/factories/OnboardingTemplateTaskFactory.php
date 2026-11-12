<?php

namespace Database\Factories;

use App\Enums\TaskAssignee;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An HR task due on the start date.
 *
 * @extends Factory<OnboardingTemplateTask>
 */
class OnboardingTemplateTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_template_id' => OnboardingTemplate::factory(),
            'title_ar' => 'مهمة',
            'title_en' => ucfirst(fake()->words(3, true)),
            'assignee' => TaskAssignee::Hr,
            'due_offset_days' => 0,
        ];
    }
}
