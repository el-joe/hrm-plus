<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\DataScopeType;
use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Development and demo accounts only — never run in production.
 * Credentials are documented in docs/DEV_ACCOUNTS.md.
 */
class DevelopmentAccountsSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(PermissionCatalogue $catalogue): void
    {
        foreach (array_keys($catalogue->defaultRoles()) as $roleName) {
            $admin = $this->account(Str::slug($roleName, '.').'@hrmplus.test', $roleName, AccountType::Admin);
            $admin->syncRoles([$roleName]);
        }

        // Restricted admins, to demonstrate data scopes, on records of the
        // OrganisationStructureSeeder (skipped when they are not seeded).
        $this->scope('branch.manager@hrmplus.test', DataScopeType::Branch, Branch::query()->where('code', 'ALX')->value('id'));
        $this->scope('department.manager@hrmplus.test', DataScopeType::Department, Department::query()->where('code', 'IT')->value('id'));

        foreach ([1, 2, 3] as $number) {
            $this->account("employee{$number}@hrmplus.test", "Employee {$number}", AccountType::Employee);
        }

        $this->linkEmployees();
    }

    /**
     * Link accounts to demo employee profiles (EmployeeSeeder::ACCOUNT_POSITIONS):
     * the employee accounts and some admins who are employees themselves
     * (they can open the portal for their own record).
     */
    private function linkEmployees(): void
    {
        foreach (EmployeeSeeder::ACCOUNT_POSITIONS as $email => $positionCode) {
            $employee = Employee::query()->withoutGlobalScopes()
                ->whereHas('position', fn ($query) => $query->where('code', $positionCode))
                ->where('status', EmployeeStatus::Active)
                ->orderBy('employee_number')
                ->first();
            $user = User::query()->where('email', $email)->first();

            if ($employee === null || $user === null) {
                continue;
            }

            $user->forceFill(['employee_id' => $employee->id, 'name' => $employee->name_en])->save();
        }
    }

    private function account(string $email, string $name, AccountType $type): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'password' => self::PASSWORD,
            'account_type' => $type,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ])->save();

        return $user;
    }

    private function scope(string $email, DataScopeType $type, ?int $id): void
    {
        if ($id === null) {
            return;
        }

        User::query()->where('email', $email)->firstOrFail()
            ->dataScopes()->firstOrCreate(['scope_type' => $type, 'scope_id' => $id]);
    }
}
