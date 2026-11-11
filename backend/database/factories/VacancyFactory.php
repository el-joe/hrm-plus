<?php

namespace Database\Factories;

use App\Enums\VacancyStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacancy>
 */
class VacancyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'VAC-'.fake()->unique()->numerify('9####'),
            'title_ar' => 'وظيفة '.fake()->numerify('###'),
            'title_en' => fake()->jobTitle(),
            'description_en' => fake()->sentence(),
            'branch_id' => Branch::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->create(['branch_id' => $attributes['branch_id']]),
            'openings' => 1,
            'currency' => 'USD',
            'status' => VacancyStatus::Open,
            'is_public' => true,
            'publish_at' => today()->subDay(),
            'application_deadline' => today()->addMonth(),
        ];
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn () => ['department_id' => $department->id, 'branch_id' => $department->branch_id]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => VacancyStatus::Draft]);
    }
}
