<?php

namespace Database\Factories;

use App\Models\ApplicationStageChange;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A move of an application into a stage (stage names are snapshots).
 *
 * @extends Factory<ApplicationStageChange>
 */
class ApplicationStageChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'to_stage_name' => 'Interview',
        ];
    }
}
