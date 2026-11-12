<?php

namespace Database\Factories;

use App\Enums\HandoverCategory;
use App\Enums\TaskAssignee;
use App\Enums\TaskStatus;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending HR hand-over item of an offboarding case.
 *
 * @extends Factory<OffboardingItem>
 */
class OffboardingItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offboarding_case_id' => OffboardingCase::factory(),
            'category' => HandoverCategory::Other,
            'title' => ucfirst(fake()->words(3, true)),
            'assignee' => TaskAssignee::Hr,
            'status' => TaskStatus::Pending,
        ];
    }
}
