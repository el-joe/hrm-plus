<?php

namespace Database\Factories;

use App\Models\SalaryComponent;
use App\Models\SalaryStructureLine;
use App\Models\SalaryStructureVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A structure line using the component's own amount.
 *
 * @extends Factory<SalaryStructureLine>
 */
class SalaryStructureLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary_structure_version_id' => SalaryStructureVersion::factory(),
            'salary_component_id' => SalaryComponent::factory(),
            'amount' => null,
        ];
    }
}
