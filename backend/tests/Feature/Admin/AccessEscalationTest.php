<?php

use App\Models\ApprovalWorkflowStep;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;

/**
 * An admin who may manage admin accounts and holds reports.view, nothing more.
 */
function accountManager(): User
{
    return adminWithPermissions('admin_users.view', 'admin_users.create', 'admin_users.update', 'reports.view');
}

function customRole(string $name, string ...$permissions): Role
{
    seedRoles();

    return tap(Role::create(['name' => $name, 'guard_name' => 'admin']))->syncPermissions($permissions);
}

function newAdminPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Omar Said',
        'email' => 'omar@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'status' => 'active',
        'roles' => [customRole('Report reader', 'reports.view')->id],
    ], $overrides);
}

/**
 * The edit-form payload of an existing admin, unchanged unless overridden.
 */
function existingAdminPayload(User $admin, array $overrides = []): array
{
    return array_merge([
        'name' => $admin->name,
        'email' => $admin->email,
        'status' => $admin->status->value,
        'roles' => $admin->roles->pluck('id')->all(),
        'scopes' => $admin->dataScopeIds(),
    ], $overrides);
}

describe('roles an admin may hand out', function () {
    it('lets an admin assign a role whose permissions they all hold', function () {
        $this->actingAs(accountManager(), 'admin')
            ->post('/admin/admin-users', newAdminPayload())
            ->assertSessionHasNoErrors();

        expect(User::query()->where('email', 'omar@example.com')->first()->hasRole('Report reader'))->toBeTrue();
    });

    it('refuses a role that carries permissions the admin does not hold', function () {
        $this->actingAs(accountManager(), 'admin')
            ->post('/admin/admin-users', newAdminPayload(['roles' => [Role::findByName('Payroll Accountant', 'admin')->id]]))
            ->assertSessionHasErrors(['roles.0' => __('app.admin_users.cannot_grant_role', ['role' => __('permissions.roles.Payroll Accountant')])]);

        $this->assertDatabaseMissing('users', ['email' => 'omar@example.com']);
    });

    it('keeps a role the admin could not grant when other fields of the account are edited', function () {
        $target = adminWithRoles('Payroll Accountant');

        $this->actingAs(accountManager(), 'admin')
            ->put("/admin/admin-users/{$target->id}", existingAdminPayload($target, ['name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        expect($target->fresh()->name)->toBe('Renamed')
            ->and($target->fresh()->hasRole('Payroll Accountant'))->toBeTrue();
    });
});

describe('own account', function () {
    it('does not let an admin change their own roles, a Super Admin included', function (Closure $makeActor) {
        $actor = $makeActor();
        $before = $actor->roles->pluck('name')->all();

        $this->actingAs($actor, 'admin')
            ->put("/admin/admin-users/{$actor->id}", existingAdminPayload($actor, [
                'roles' => [...$actor->roles->pluck('id')->all(), customRole('Extra', 'reports.view')->id],
            ]))
            ->assertSessionHasErrors(['roles' => __('app.admin_users.cannot_change_own_access')]);

        expect($actor->fresh()->roles->pluck('name')->all())->toBe($before);
    })->with([
        'account manager' => [fn () => accountManager()],
        'super admin' => [fn () => superAdmin()],
    ]);

    it('does not let an admin lift their own data restrictions', function () {
        $actor = accountManager();
        $actor->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => 1]);
        $actor->unsetRelation('dataScopes');

        $this->actingAs($actor, 'admin')
            ->put("/admin/admin-users/{$actor->id}", existingAdminPayload($actor, ['scopes' => []]))
            ->assertSessionHasErrors('roles');

        expect($actor->fresh()->dataScopeIds())->toBe(['branch' => [1]]);
    });

    it('still lets an admin edit their own name', function () {
        $actor = accountManager();

        $this->actingAs($actor, 'admin')
            ->put("/admin/admin-users/{$actor->id}", existingAdminPayload($actor, ['name' => 'New name']))
            ->assertSessionHasNoErrors();

        expect($actor->fresh()->name)->toBe('New name');
    });
});

describe('data scopes a restricted admin may hand out', function () {
    beforeEach(function () {
        // Data scopes must point at existing branches / departments.
        Branch::factory()->create(['id' => 1]);
        Branch::factory()->create(['id' => 2]);
        Department::factory()->create(['id' => 9]);

        $this->actor = accountManager();
        $this->actor->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => 1]);
        $this->actor->unsetRelation('dataScopes');
    });

    it('accepts scopes inside the admin\'s own', function () {
        $this->actingAs($this->actor, 'admin')
            ->post('/admin/admin-users', newAdminPayload(['scopes' => ['branch' => '1']]))
            ->assertSessionHasNoErrors();

        expect(User::query()->where('email', 'omar@example.com')->first()->dataScopeIds())->toBe(['branch' => [1]]);
    });

    it('refuses an unrestricted account or scopes outside the admin\'s own', function (array $scopes) {
        $this->actingAs($this->actor, 'admin')
            ->post('/admin/admin-users', newAdminPayload(['scopes' => $scopes]))
            ->assertSessionHasErrors('scopes');

        $this->assertDatabaseMissing('users', ['email' => 'omar@example.com']);
    })->with([
        'no restriction' => [[]],
        'another branch' => [['branch' => '1, 2']],
        'a dimension the admin is not restricted by' => [['branch' => '1', 'department' => '9']],
    ]);
});

describe('role permissions an admin may hand out', function () {
    it('lets an admin edit a role that holds permissions they lack, without adding any', function () {
        $actor = adminWithPermissions('roles.view', 'roles.update', 'reports.view');
        $role = customRole('Payroll helper', 'payroll.view');

        $this->actingAs($actor, 'admin')
            ->put("/admin/roles/{$role->id}", ['name' => 'Payroll helper', 'description' => 'Renamed', 'permissions' => ['payroll.view', 'reports.view']])
            ->assertSessionHasNoErrors();

        expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['payroll.view', 'reports.view']);
    });

    it('still refuses to add a permission the admin lacks', function () {
        $actor = adminWithPermissions('roles.view', 'roles.update', 'reports.view');
        $role = customRole('Payroll helper', 'payroll.view');

        $this->actingAs($actor, 'admin')
            ->put("/admin/roles/{$role->id}", ['name' => 'Payroll helper', 'permissions' => ['payroll.view', 'payroll.approve']])
            ->assertSessionHasErrors('permissions.1');

        expect($role->fresh()->permissions->pluck('name')->all())->toBe(['payroll.view']);
    });
});

it('refuses to delete a role that is an approver in a workflow', function () {
    $role = customRole('Leave approver', 'leaves.approve');
    ApprovalWorkflowStep::factory()->approvedByRole($role)->create();

    $this->actingAs(superAdmin(), 'admin')
        ->delete("/admin/roles/{$role->id}")
        ->assertSessionHasErrors(['role' => __('app.roles.cannot_delete_approver')]);

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});
