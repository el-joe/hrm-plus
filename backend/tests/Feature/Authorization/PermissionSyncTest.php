<?php

use App\Models\Role;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('adds catalogue permissions that appeared since the last sync to the default roles, once', function () {
    seedRoles();

    // An install from before this release: the permission did not exist and the role lacks it.
    Permission::findByName('employee_sensitive.view', 'admin')->delete();
    DB::table('role_default_grants')->where('permission', 'employee_sensitive.view')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $specialist = Role::findByName('HR Specialist', 'admin');
    expect($specialist->hasPermissionTo('employees.view'))->toBeTrue();

    $this->artisan('permissions:sync')->assertSuccessful()->expectsOutputToContain('HR Specialist');

    expect($specialist->fresh()->hasPermissionTo('employee_sensitive.view'))->toBeTrue()
        ->and(Role::findByName('Attendance Officer', 'admin')->hasPermissionTo('employee_sensitive.view'))->toBeFalse();
});

it('never re-grants a default permission an administrator removed, and keeps custom grants', function () {
    seedRoles();
    $role = Role::findByName('HR Specialist', 'admin');
    $role->revokePermissionTo('employee_groups.view');
    $role->givePermissionTo('payroll.view');

    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('permissions:sync')->assertSuccessful()->expectsOutputToContain('already up to date');

    $role = $role->fresh();
    expect($role->hasPermissionTo('employee_groups.view'))->toBeFalse()
        ->and($role->hasPermissionTo('payroll.view'))->toBeTrue();
});

it('grants a permission newly added to a role\'s default set on existing installs', function () {
    seedRoles();
    $permissions = config('permissions.roles.Reports Viewer.permissions');
    Config::set('permissions.roles.Reports Viewer.permissions', [...$permissions, 'employees.view']);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Role::findByName('Reports Viewer', 'admin')->hasPermissionTo('employees.view'))->toBeTrue();
});

it('is run by the roles seeder', function () {
    seedRoles();

    expect(DB::table('role_default_grants')->count())->toBeGreaterThan(100)
        ->and(Role::findByName('Super Admin', 'admin')->permissions()->count())->toBe(Permission::query()->count());
});
