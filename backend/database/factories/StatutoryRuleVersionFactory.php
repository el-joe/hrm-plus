<?php

namespace Database\Factories;

use App\Models\StatutoryRuleSet;
use App\Models\StatutoryRuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Version 1 of a rule set: 11 % employee and 18.75 % employer, floor 2000, ceiling 12000.
 *
 * @extends Factory<StatutoryRuleVersion>
 */
class StatutoryRuleVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statutory_rule_set_id' => StatutoryRuleSet::factory(),
            'version' => 1,
            'effective_from' => '2025-01-01',
            'employee_rate' => '11.0000',
            'employer_rate' => '18.7500',
            'salary_floor' => '2000.00',
            'salary_ceiling' => '12000.00',
            'base_reductions' => [],
            'deduct_employee_insurance' => false,
        ];
    }
}
