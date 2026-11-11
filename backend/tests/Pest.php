<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// Tests\TestCase refreshes the database for every test.
pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Admin accounts are only meaningful with roles, so these helpers seed the
| permission catalogue and the default roles on first use in a test.
|
*/

/**
 * Seed the permission catalogue and the default roles (once per test).
 */
function seedRoles(): void
{
    if (Permission::query()->doesntExist()) {
        test()->seed(RolesAndPermissionsSeeder::class);
    }
}

/**
 * An active admin holding the given default roles, e.g. adminWithRoles('Attendance Officer').
 */
function adminWithRoles(string ...$roles): User
{
    seedRoles();

    return User::factory()->admin()->withRoles(...$roles)->create();
}

function superAdmin(): User
{
    return adminWithRoles('Super Admin');
}

/**
 * An active admin with a custom role holding exactly the given permissions.
 */
function adminWithPermissions(string ...$permissions): User
{
    seedRoles();

    $role = Role::create(['name' => 'Test role '.uniqid(), 'guard_name' => 'admin']);
    $role->syncPermissions($permissions);

    return User::factory()->admin()->create()->assignRole($role);
}

/**
 * A small organisation for employee tests: a branch, a department in it, an
 * employment type and a position (headcount 2) in the department.
 *
 * @return array{branch: Branch, department: Department, type: EmploymentType, position: Position}
 */
function hrStructure(): array
{
    $branch = Branch::factory()->create(['code' => 'CAI', 'name_en' => 'Cairo']);
    $department = Department::factory()->create(['code' => 'IT', 'branch_id' => $branch->id, 'name_en' => 'Information technology']);
    $type = EmploymentType::factory()->create(['code' => 'FT']);
    $position = Position::factory()->create(['code' => 'DEV', 'department_id' => $department->id, 'headcount' => 2]);

    return compact('branch', 'department', 'type', 'position');
}

/**
 * Valid input of the employee create form for the given structure.
 *
 * @param  array{branch: Branch, department: Department, type: EmploymentType, position?: Position}  $structure
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(array $structure, array $overrides = []): array
{
    return [
        'name_ar' => 'سارة أحمد',
        'name_en' => 'Sara Ahmed',
        'email' => 'sara.ahmed@example.test',
        'hire_date' => '2024-03-01',
        'branch_id' => $structure['branch']->id,
        'department_id' => $structure['department']->id,
        'employment_type_id' => $structure['type']->id,
        ...$overrides,
    ];
}
