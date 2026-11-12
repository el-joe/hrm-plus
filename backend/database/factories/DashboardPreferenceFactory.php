<?php

namespace Database\Factories;

use App\Models\DashboardPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An admin's dashboard layout (default order, nothing hidden).
 *
 * @extends Factory<DashboardPreference>
 */
class DashboardPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'order' => [],
            'hidden' => [],
        ];
    }
}
