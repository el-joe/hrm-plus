<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function roleId(string $name): int
{
    seedRoles();

    return Role::findByName($name, 'admin')->id;
}

/**
 * A valid payload for creating or updating an admin account.
 */
function adminUserPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mona Hassan',
        'email' => 'mona@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'status' => 'active',
        'locale' => 'ar',
        'roles' => [roleId('Attendance Officer')],
    ], $overrides);
}

describe('index', function () {
    it('lists admin accounts only', function () {
        $viewer = adminWithPermissions('admin_users.view');
        User::factory()->admin()->create(['name' => 'Listed Admin']);
        User::factory()->employee()->create(['name' => 'Hidden Employee']);

        $this->actingAs($viewer, 'admin')
            ->get('/admin/admin-users')
            ->assertOk()
            ->assertSee('Listed Admin')
            ->assertDontSee('Hidden Employee');
    });

    it('filters by role', function () {
        $viewer = adminWithPermissions('admin_users.view');
        adminWithRoles('Recruiter')->update(['name' => 'The Recruiter']);
        adminWithRoles('Finance Officer')->update(['name' => 'The Finance Officer']);

        $this->actingAs($viewer, 'admin')
            ->get('/admin/admin-users?role='.roleId('Recruiter'))
            ->assertSee('The Recruiter')
            ->assertDontSee('The Finance Officer');
    });
});

describe('store', function () {
    beforeEach(function () {
        // Data scopes must point at existing branches / departments.
        foreach ([3, 7] as $id) {
            Branch::factory()->create(['id' => $id]);
        }
        Department::factory()->create(['id' => 12]);
        Employee::factory()->create(['id' => 42]);
    });

    it('creates an admin with several roles, an employee link and data scopes', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', adminUserPayload([
                'roles' => [roleId('Branch Manager'), roleId('Attendance Officer')],
                'employee_id' => 42,
                'scopes' => ['branch' => '3, 7', 'department' => ['12']],
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', __('app.admin_users.created'));

        $admin = User::query()->where('email', 'mona@example.com')->sole();

        expect($admin->isAdmin())->toBeTrue()
            ->and($admin->employee_id)->toBe(42)
            ->and(Hash::check('secret-pass-1', $admin->password))->toBeTrue()
            ->and($admin->getRoleNames()->sort()->values()->all())->toBe(['Attendance Officer', 'Branch Manager'])
            ->and($admin->dataScopeIds())->toBe(['branch' => [3, 7], 'department' => [12]]);
    });

    it('audits the role assignment and the data scopes without storing the password', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', adminUserPayload(['scopes' => ['branch' => '3']]));

        $admin = User::query()->where('email', 'mona@example.com')->sole();

        expect(AuditLog::query()->where('event', 'admin_user.roles_updated')->where('auditable_id', $admin->id)->sole()->new_values)
            ->toBe(['roles' => ['Attendance Officer']]);

        expect(AuditLog::query()->where('event', 'admin_user.data_scopes_updated')->sole()->new_values)
            ->toBe(['scopes' => ['branch' => [3]]]);

        expect(AuditLog::query()->get()->toJson())
            ->not->toContain('secret-pass-1')
            ->not->toContain($admin->password);
    });

    it('returns 403 without the create permission', function () {
        $this->actingAs(adminWithPermissions('admin_users.view'), 'admin')
            ->post('/admin/admin-users', adminUserPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'mona@example.com']);
    });

    it('requires the core fields', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', [])
            ->assertSessionHasErrors(['name', 'email', 'password', 'status', 'roles']);
    });

    it('rejects an email that is already used', function () {
        User::factory()->employee()->create(['email' => 'mona@example.com']);

        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', adminUserPayload())
            ->assertSessionHasErrors(['email' => __('validation.unique', ['attribute' => __('validation.attributes.email')])]);
    });

    it('rejects an employee profile that does not exist', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', adminUserPayload(['employee_id' => 999999]))
            ->assertSessionHasErrors('employee_id');
    });

    it('rejects an employee profile that is already linked to another account', function () {
        User::factory()->employee()->create(['employee_id' => 42]);

        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', adminUserPayload(['employee_id' => 42]))
            ->assertSessionHasErrors('employee_id');
    });

    it('rejects an unknown data scope type and non-numeric ids', function (array $scopes, string $errorKey) {
        $this->actingAs(superAdmin(), 'admin')
            ->post('/admin/admin-users', adminUserPayload(['scopes' => $scopes]))
            ->assertSessionHasErrors($errorKey);
    })->with([
        'unknown type' => [['planet' => '1'], 'scopes'],
        'non-numeric id' => [['branch' => 'abc'], 'scopes.branch.0'],
    ]);

    it('does not let a non Super Admin assign the Super Admin role', function () {
        $this->actingAs(adminWithPermissions('admin_users.create'), 'admin')
            ->post('/admin/admin-users', adminUserPayload(['roles' => [roleId('Super Admin')]]))
            ->assertSessionHasErrors(['roles.0' => __('app.admin_users.cannot_grant_super_admin')]);

        $this->assertDatabaseMissing('users', ['email' => 'mona@example.com']);
    });
});

describe('update', function () {
    it('updates the account, replaces its roles and audits the change', function () {
        $target = adminWithRoles('Recruiter');

        $this->actingAs(superAdmin(), 'admin')
            ->put('/admin/admin-users/'.$target->id, adminUserPayload([
                'name' => 'Renamed',
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'roles' => [roleId('HR Specialist')],
            ]))
            ->assertRedirect(route('admin.admin-users.show', $target));

        $target->refresh();

        expect($target->name)->toBe('Renamed')
            ->and($target->getRoleNames()->all())->toBe(['HR Specialist'])
            ->and(Hash::check('password', $target->password))->toBeTrue();

        $log = AuditLog::query()->where('event', 'admin_user.roles_updated')->where('auditable_id', $target->id)->latest('id')->first();

        expect($log->old_values)->toBe(['roles' => ['Recruiter']])
            ->and($log->new_values)->toBe(['roles' => ['HR Specialist']]);
    });

    it('removes all data scopes when they are cleared', function () {
        $target = adminWithRoles('Branch Manager');
        $target->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => 5]);

        $this->actingAs(superAdmin(), 'admin')
            ->put('/admin/admin-users/'.$target->id, adminUserPayload([
                'email' => $target->email, 'password' => '', 'password_confirmation' => '',
                'scopes' => ['branch' => ''],
            ]))
            ->assertSessionHasNoErrors();

        expect($target->fresh()->dataScopes)->toBeEmpty();
    });

    it('does not let an admin deactivate their own account', function () {
        $actor = superAdmin();

        $this->actingAs($actor, 'admin')
            ->put('/admin/admin-users/'.$actor->id, adminUserPayload([
                'email' => $actor->email, 'password' => '', 'password_confirmation' => '',
                'status' => 'inactive', 'roles' => [roleId('Super Admin')],
            ]))
            ->assertSessionHasErrors(['status' => __('app.admin_users.cannot_deactivate_self')]);

        expect($actor->fresh()->isActive())->toBeTrue();
    });

    it('returns 403 when a non Super Admin edits a Super Admin', function () {
        $target = superAdmin();

        $this->actingAs(adminWithPermissions('admin_users.update'), 'admin')
            ->put('/admin/admin-users/'.$target->id, adminUserPayload(['email' => $target->email]))
            ->assertForbidden();
    });

    it('returns 404 for an employee account', function () {
        $employee = User::factory()->employee()->create();

        $this->actingAs(superAdmin(), 'admin')
            ->get('/admin/admin-users/'.$employee->id.'/edit')
            ->assertNotFound();
    });
});

describe('destroy', function () {
    it('soft deletes an admin and audits it', function () {
        $target = adminWithRoles('Recruiter');

        $this->actingAs(superAdmin(), 'admin')
            ->delete('/admin/admin-users/'.$target->id)
            ->assertRedirect(route('admin.admin-users.index'));

        $this->assertSoftDeleted($target);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $target->id]);
    });

    it('returns 403 when an admin deletes their own account, even a Super Admin', function () {
        $actor = superAdmin();

        $this->actingAs($actor, 'admin')
            ->delete('/admin/admin-users/'.$actor->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($actor);
    });

    it('returns 403 without the delete permission', function () {
        $target = adminWithRoles('Recruiter');

        $this->actingAs(adminWithPermissions('admin_users.view', 'admin_users.update'), 'admin')
            ->delete('/admin/admin-users/'.$target->id)
            ->assertForbidden();
    });
});

describe('access history', function () {
    it('shows the roles, effective permissions and recent sign-ins of an admin', function () {
        $target = adminWithRoles('Reports Viewer');
        LoginHistory::factory()->for($target)->failed()->create(['ip_address' => '203.0.113.9']);

        $this->actingAs(adminWithPermissions('admin_users.view'), 'admin')
            ->get('/admin/admin-users/'.$target->id)
            ->assertOk()
            ->assertSee('reports.export')
            ->assertSee('203.0.113.9');
    });

    it('lists the full login history of one admin only', function () {
        $target = adminWithRoles('Reports Viewer');
        LoginHistory::factory()->for($target)->create(['ip_address' => '203.0.113.9']);
        LoginHistory::factory()->create(['ip_address' => '198.51.100.77']);

        $this->actingAs(adminWithPermissions('admin_users.view'), 'admin')
            ->get('/admin/admin-users/'.$target->id.'/login-history')
            ->assertOk()
            ->assertSee('203.0.113.9')
            ->assertDontSee('198.51.100.77');
    });

    it('returns 403 for the login history without the view permission', function () {
        $target = adminWithRoles('Reports Viewer');

        $this->actingAs(adminWithPermissions('roles.view'), 'admin')
            ->get('/admin/admin-users/'.$target->id.'/login-history')
            ->assertForbidden();
    });
});
