<?php

use App\Models\Role;
use App\Services\Authorization\PermissionCatalogue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

test('every permission is named module.action using a known action', function () {
    $catalogue = app(PermissionCatalogue::class);

    foreach ($catalogue->all() as $permission) {
        [$module, $action] = explode('.', $permission);

        expect($catalogue->modules())->toHaveKey($module)
            ->and($catalogue->actions())->toContain($action);
    }
});

test('the seeder creates the catalogue and the eleven default roles of the spec', function () {
    seedRoles();

    expect(Permission::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(app(PermissionCatalogue::class)->all())->sort()->values()->all());

    expect(Role::query()->where('is_system', true)->orderBy('name')->pluck('name')->all())->toBe([
        'Attendance Officer', 'Branch Manager', 'Department Manager', 'Direct Manager', 'Finance Officer',
        'HR Manager', 'HR Specialist', 'Payroll Accountant', 'Recruiter', 'Reports Viewer', 'Super Admin',
    ]);
});

test('the Super Admin role holds every permission', function () {
    seedRoles();

    expect(Role::findByName('Super Admin', 'admin')->permissions()->count())
        ->toBe(count(app(PermissionCatalogue::class)->all()));
});

test('re-running the seeder keeps the permissions an admin gave to an existing role', function () {
    seedRoles();
    $role = Role::findByName('Reports Viewer', 'admin');
    $role->syncPermissions(['reports.view']);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['reports.view']);
});

test('expanding an unknown permission pattern fails loudly', function (string $pattern) {
    app(PermissionCatalogue::class)->expand([$pattern]);
})->with(['payrol.view', 'payroll.fly', 'nothing.*'])->throws(InvalidArgumentException::class);
