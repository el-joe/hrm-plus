<?php

namespace Database\Factories;

use App\Models\StatutoryRuleBracket;
use App\Models\StatutoryRuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 10 % tax bracket from 30000 to 60000.
 *
 * @extends Factory<StatutoryRuleBracket>
 */
class StatutoryRuleBracketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statutory_rule_version_id' => StatutoryRuleVersion::factory(),
            'lower_bound' => '30000.00',
            'upper_bound' => '60000.00',
            'rate' => '10.0000',
        ];
    }
}
