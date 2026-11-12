<?php

namespace Database\Factories;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active hand-over of an asset to an employee (the asset's status is not changed;
 * use App\Actions\Assets\* for consistent state).
 *
 * @extends Factory<AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'employee_id' => Employee::factory(),
            'assigned_on' => now()->subWeek()->toDateString(),
            'condition_on_issue' => AssetCondition::Good,
            'status' => AssetAssignmentStatus::Active,
        ];
    }
}
