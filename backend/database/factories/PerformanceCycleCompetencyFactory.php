<?php

namespace Database\Factories;

use App\Models\Competency;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleCompetency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A competency weighted 20 % in a performance cycle.
 *
 * @extends Factory<PerformanceCycleCompetency>
 */
class PerformanceCycleCompetencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_id' => PerformanceCycle::factory(),
            'competency_id' => Competency::factory(),
            'weight' => '20.00',
        ];
    }
}
