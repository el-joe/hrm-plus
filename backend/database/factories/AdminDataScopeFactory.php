<?php

namespace Database\Factories;

use App\Enums\DataScopeType;
use App\Models\AdminDataScope;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Restricts an admin account to one branch.
 *
 * @extends Factory<AdminDataScope>
 */
class AdminDataScopeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'scope_type' => DataScopeType::Branch,
            'scope_id' => Branch::factory(),
        ];
    }
}
