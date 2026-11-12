<?php

namespace Database\Factories;

use App\Enums\HrRequestPriority;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open portal request due in 48 hours. Tests that exercise the workflow should go through
 * App\Actions\HrRequests\SubmitHrRequest instead.
 *
 * @extends Factory<HrRequest>
 */
class HrRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'HRQ-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'employee_id' => Employee::factory(),
            'hr_request_category_id' => HrRequestCategory::factory(),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => HrRequestPriority::Normal,
            'status' => HrRequestStatus::Open,
            'source' => HrRequestSource::Portal,
            'opened_at' => now(),
            'due_at' => now()->addHours(48),
        ];
    }

    public function overdue(int $hours = 5): static
    {
        return $this->state(fn (array $attributes) => ['due_at' => now()->subHours($hours), 'opened_at' => now()->subHours(48 + $hours)]);
    }
}
