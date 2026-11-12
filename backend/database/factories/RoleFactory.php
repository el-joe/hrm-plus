<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A custom (non-system) admin role without permissions.
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Role '.fake()->unique()->numberBetween(1, 9_999_999),
            'guard_name' => config('permissions.guard'),
            'is_system' => false,
        ];
    }
}
