<?php

use App\Models\Role;
use App\Services\Authorization\AdminAccessManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/*
| permissions:sync (Phase 8b): a permission dropped from a default role's default set is revoked only when
| sync granted it and nobody re-granted it by hand; --dry-run reports without saving.
*/

function dropFromDefaults(string $role, string $permission): void
{
    $permissions = config("permissions.roles.{$role}.permissions");
    Config::set("permissions.roles.{$role}.permissions", array_values(array_diff($permissions, [$permission])));
}

it('revokes a sync-granted permission removed from the default set and reports it', function () {
    seedRoles();
    $role = Role::findByName('HR Specialist', 'admin');
    expect($role->hasPermissionTo('reports.view'))->toBeTrue();

    dropFromDefaults('HR Specialist', 'reports.view');

    $this->artisan('permissions:sync')->assertSuccessful()->expectsOutputToContain('reports.view');

    expect($role->fresh()->hasPermissionTo('reports.view'))->toBeFalse()
        ->and(DB::table('role_default_grants')->where('role_id', $role->id)->where('permission', 'reports.view')->exists())->toBeFalse();
});

it('keeps a dropped permission that an administrator re-granted by hand', function () {
    seedRoles();
    $role = Role::findByName('HR Specialist', 'admin');
    $access = app(AdminAccessManager::class);
    $current = $role->permissions()->pluck('name')->all();

    // The admin removes it, then grants it back through the role screen.
    $access->syncRolePermissions($role, array_values(array_diff($current, ['reports.view'])));
    $access->syncRolePermissions($role->fresh(), $current);
    dropFromDefaults('HR Specialist', 'reports.view');

    $this->artisan('permissions:sync')->assertSuccessful()->expectsOutputToContain('kept');

    expect($role->fresh()->hasPermissionTo('reports.view'))->toBeTrue();
});

it('keeps a dropped permission the role already held before sync offered it', function () {
    seedRoles();
    $role = Role::findByName('Reports Viewer', 'admin');
    $role->givePermissionTo('employees.view');
    $permissions = config('permissions.roles.Reports Viewer.permissions');
    Config::set('permissions.roles.Reports Viewer.permissions', [...$permissions, 'employees.view']);
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(DB::table('role_default_grants')->where('role_id', $role->id)->where('permission', 'employees.view')->value('granted_by_sync'))->toBeFalsy();

    Config::set('permissions.roles.Reports Viewer.permissions', $permissions);
    $this->artisan('permissions:sync')->assertSuccessful();

    expect($role->fresh()->hasPermissionTo('employees.view'))->toBeTrue();
});

it('changes nothing on a dry run but prints what would be revoked', function () {
    seedRoles();
    $role = Role::findByName('HR Specialist', 'admin');
    dropFromDefaults('HR Specialist', 'reports.view');
    $grants = DB::table('role_default_grants')->count();

    $this->artisan('permissions:sync --dry-run')->assertSuccessful()
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('reports.view');

    expect($role->fresh()->hasPermissionTo('reports.view'))->toBeTrue()
        ->and(DB::table('role_default_grants')->count())->toBe($grants);
});

it('offers a revoked permission again if it returns to the default set', function () {
    seedRoles();
    $role = Role::findByName('HR Specialist', 'admin');
    $permissions = config('permissions.roles.HR Specialist.permissions');
    dropFromDefaults('HR Specialist', 'reports.view');
    $this->artisan('permissions:sync')->assertSuccessful();

    Config::set('permissions.roles.HR Specialist.permissions', $permissions);
    $this->artisan('permissions:sync')->assertSuccessful();

    expect($role->fresh()->hasPermissionTo('reports.view'))->toBeTrue();
});

it('never revokes anything from Super Admin', function () {
    seedRoles();
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Role::findByName('Super Admin', 'admin')->hasPermissionTo('settings.manage'))->toBeTrue();
});
