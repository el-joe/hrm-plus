<?php

namespace Database\Factories;

use App\Enums\DisciplinaryCaseStatus;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\ViolationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A reported case. Prefer App\Actions\Disciplinary\ManageDisciplinaryCase::report() in
 * behaviour tests; the factory is for list / visibility fixtures.
 *
 * @extends Factory<DisciplinaryCase>
 */
class DisciplinaryCaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'DIS-TEST-'.fake()->unique()->numberBetween(10000, 99999),
            'employee_id' => Employee::factory(),
            'violation_type_id' => ViolationType::factory(),
            'branch_id' => fn (array $attributes) => Employee::query()->withoutGlobalScopes()->find($attributes['employee_id'])?->branch_id,
            'department_id' => fn (array $attributes) => Employee::query()->withoutGlobalScopes()->find($attributes['employee_id'])?->department_id,
            'incident_date' => now()->subDays(3)->toDateString(),
            'description' => 'Incident description',
            'status' => DisciplinaryCaseStatus::Reported,
        ];
    }
}
