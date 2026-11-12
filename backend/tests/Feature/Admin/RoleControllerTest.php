<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

beforeEach(fn () => seedRoles());

describe('index and forms', function () {
    it('lists the roles', function () {
        $this->actingAs(adminWithPermissions('roles.view'), 'admin')
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee(__('permissions.roles.Payroll Accountant'));
    });

    it('renders the permission matrix with the role\'s permissions ticked', function () {
        $actor = superAdmin();
        $role = Role::findByName('Reports Viewer', 'admin');

        $response = $this->actingAs($actor, 'admin')->get('/admin/roles/'.$role->id.'/edit')->assertOk();

        expect($response->viewData('granted'))->toEqualCanonicalizing(['dashboard.view', 'reports.view', 'reports.export', 'reports.manage'])
            ->and($response->viewData('modules'))->toHaveKey('payroll');

        $response->assertSee('value="payroll.approve"', false);
    });
});

describe('store', function () {
    it('creates a custom role with the chosen permissions and audits them', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/roles', [
                'name' => 'Night Shift Supervisor',
                'description' => 'Night attendance only',
                'permissions' => ['attendance.view', 'attendance.update'],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('app.roles.created'));

        $role = Role::findByName('Night Shift Supervisor', 'admin');

        expect($role->is_system)->toBeFalse()
            ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['attendance.update', 'attendance.view']);

        expect(AuditLog::query()->where('event', 'role.permissions_updated')->where('auditable_id', $role->id)->sole()->new_values)
            ->toBe(['permissions' => ['attendance.update', 'attendance.view']]);
    });

    it('returns 403 without the create permission', function () {
        $this->actingAs(adminWithPermissions('roles.view'), 'admin')
            ->post('/admin/roles', ['name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Sneaky']);
    });

    it('rejects a duplicate role name', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/roles', ['name' => 'Recruiter'])
            ->assertSessionHasErrors('name');
    });

    it('rejects a permission that is not in the catalogue', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/roles', ['name' => 'Odd', 'permissions' => ['payroll.destroy_everything']])
            ->assertSessionHasErrors('permissions.0');
    });

    it('does not let an admin grant a permission they do not hold', function () {
        $this->actingAs(adminWithPermissions('roles.create', 'attendance.view'), 'admin')
            ->post('/admin/roles', ['name' => 'Escalation', 'permissions' => ['attendance.view', 'payroll.approve']])
            ->assertSessionHasErrors(['permissions.1' => __('app.roles.cannot_grant_permission', ['permission' => 'payroll.approve'])]);

        $this->assertDatabaseMissing('roles', ['name' => 'Escalation']);
    });
});

describe('update', function () {
    it('changes the permissions of a role and audits the old and new sets', function () {
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'admin']);
        $role->syncPermissions(['leaves.view']);
        $member = User::factory()->admin()->create()->assignRole($role);

        $this->actingAs(superAdmin(), 'admin')
            ->put('/admin/roles/'.$role->id, ['name' => 'Custom', 'permissions' => ['leaves.view', 'leaves.approve']])
            ->assertSessionHas('status', __('app.roles.updated'));

        $log = AuditLog::query()->where('event', 'role.permissions_updated')->where('auditable_id', $role->id)->latest('id')->first();

        expect($log->old_values)->toBe(['permissions' => ['leaves.view']])
            ->and($log->new_values)->toBe(['permissions' => ['leaves.approve', 'leaves.view']])
            ->and($log->user_id)->not->toBeNull()
            ->and($member->fresh()->can('leaves.approve'))->toBeTrue();
    });

    it('keeps the name of a system role', function () {
        $role = Role::findByName('Recruiter', 'admin');

        $this->actingAs(superAdmin(), 'admin')
            ->put('/admin/roles/'.$role->id, ['name' => 'Hacked', 'permissions' => ['recruitment.view']])
            ->assertSessionHasNoErrors();

        expect($role->fresh()->name)->toBe('Recruiter')
            ->and($role->fresh()->permissions->pluck('name')->all())->toBe(['recruitment.view']);
    });

    it('returns 403 when anyone edits the Super Admin role', function () {
        $role = Role::findByName('Super Admin', 'admin');

        $this->actingAs(superAdmin(), 'admin')
            ->put('/admin/roles/'.$role->id, ['permissions' => []])
            ->assertForbidden();
    });

    it('returns 403 without the update permission', function () {
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'admin']);

        $this->actingAs(adminWithPermissions('roles.view'), 'admin')
            ->put('/admin/roles/'.$role->id, ['name' => 'Custom', 'permissions' => []])
            ->assertForbidden();
    });
});

describe('destroy', function () {
    it('deletes a custom role that nobody holds', function () {
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'admin']);

        $this->actingAs(superAdmin(), 'admin')
            ->delete('/admin/roles/'.$role->id)
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $role->id, 'auditable_type' => $role->getMorphClass()]);
    });

    it('returns 403 when deleting a system role', function () {
        $role = Role::findByName('Recruiter', 'admin');

        $this->actingAs(superAdmin(), 'admin')
            ->delete('/admin/roles/'.$role->id)
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    });

    it('refuses to delete a role that is still assigned', function () {
        $actor = superAdmin();
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'admin']);
        User::factory()->admin()->create()->assignRole($role);

        $this->actingAs($actor, 'admin')
            ->delete('/admin/roles/'.$role->id)
            ->assertSessionHasErrors(['role' => __('app.roles.cannot_delete_assigned')]);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    });
});
