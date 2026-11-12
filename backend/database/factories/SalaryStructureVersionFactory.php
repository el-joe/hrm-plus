<?php

namespace Database\Factories;

use App\Models\SalaryStructure;
use App\Models\SalaryStructureVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Version 1 of a structure, open-ended from the start of the year.
 *
 * @extends Factory<SalaryStructureVersion>
 */
class SalaryStructureVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary_structure_id' => SalaryStructure::factory(),
            'version' => 1,
            'effective_from' => now()->startOfYear()->toDateString(),
        ];
    }
}
