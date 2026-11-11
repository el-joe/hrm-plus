<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: an active employee account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::Employee,
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An admin account with no roles. Chain withRoles() to grant access.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Admin,
        ]);
    }

    /**
     * An employee account linked to an employee profile.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Employee,
            'employee_id' => Employee::factory(),
        ]);
    }

    /**
     * Link the account to an employee profile (used for admins who are also employees).
     */
    public function linkedToEmployee(?int $employeeId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId ?? Employee::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Inactive,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    /**
     * Assign existing roles (by name) after creation. The roles must exist,
     * e.g. from RolesAndPermissionsSeeder.
     */
    public function withRoles(string ...$roles): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($roles));
    }
}
