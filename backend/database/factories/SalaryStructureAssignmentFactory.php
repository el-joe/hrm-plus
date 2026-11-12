<?php

namespace Database\Factories;

use App\Enums\SalaryStructureScope;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Assigns a structure to the whole company from the start of the year.
 *
 * @extends Factory<SalaryStructureAssignment>
 */
class SalaryStructureAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary_structure_id' => SalaryStructure::factory(),
            'scope_type' => SalaryStructureScope::Company,
            'scope_id' => null,
            'effective_from' => now()->startOfYear()->toDateString(),
        ];
    }
}
