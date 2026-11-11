<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // A stand-in for a future payroll screen, protected exactly as real
    // module routes must be: "admin" middleware plus a permission.
    Route::middleware(['web', 'admin'])
        ->get('/admin/_test/payroll', fn () => 'payroll data')
        ->can('payroll.view');
});

test('an Attendance Officer manages attendance but holds no payroll or financial permission', function () {
    $officer = adminWithRoles('Attendance Officer');

    expect($officer->can('attendance.view'))->toBeTrue()
        ->and($officer->can('attendance.update'))->toBeTrue()
        ->and($officer->can('attendance.approve'))->toBeTrue();

    foreach (['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'manage'] as $action) {
        expect($officer->can('payroll.'.$action))->toBeFalse();
    }

    expect($officer->can('employee_financials.view'))->toBeFalse()
        ->and($officer->can('loans.view'))->toBeFalse();
});

test('default roles respect the separations required by the spec', function (string $role, string $allowed, string $denied) {
    $admin = adminWithRoles($role);

    expect($admin->can($allowed))->toBeTrue()
        ->and($admin->can($denied))->toBeFalse();
})->with([
    'recruiter cannot modify payroll' => ['Recruiter', 'recruitment.update', 'payroll.update'],
    'payroll accountant cannot change attendance devices' => ['Payroll Accountant', 'payroll.create', 'attendance_devices.update'],
    'payroll accountant cannot approve payroll' => ['Payroll Accountant', 'payroll.update', 'payroll.approve'],
    'finance officer cannot edit payroll' => ['Finance Officer', 'payroll.approve', 'payroll.update'],
    'reports viewer cannot see employees' => ['Reports Viewer', 'reports.view', 'employees.view'],
    'hr manager cannot manage roles' => ['HR Manager', 'employees.update', 'roles.update'],
]);

it('returns 403 to an Attendance Officer on a payroll-protected route', function () {
    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')
        ->get('/admin/_test/payroll')
        ->assertForbidden();
});

it('lets a Payroll Accountant through the payroll-protected route', function () {
    $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')
        ->get('/admin/_test/payroll')
        ->assertOk()
        ->assertSee('payroll data');
});

it('lets an admin holding several roles use the permissions of all of them', function () {
    $admin = adminWithRoles('Attendance Officer', 'Payroll Accountant');

    $this->actingAs($admin, 'admin')->get('/admin/_test/payroll')->assertOk();

    expect($admin->can('attendance.manage'))->toBeTrue();
});

it('returns 403 to an Attendance Officer on every access-control screen', function (string $uri) {
    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')
        ->get($uri)
        ->assertForbidden();
})->with(['/admin/admin-users', '/admin/admin-users/create', '/admin/roles', '/admin/roles/create', '/admin/audit-logs', '/admin/settings']);

it('returns 403 to an admin without any role on a permission-protected route', function () {
    $this->actingAs(User::factory()->admin()->create(), 'admin')
        ->get('/admin/_test/payroll')
        ->assertForbidden();
});

test('a Super Admin passes a permission that exists in the catalogue but was never granted in the database', function () {
    $superAdmin = superAdmin();
    $superAdmin->roles->first()->syncPermissions([]);

    expect($superAdmin->fresh()->can('payroll.approve'))->toBeTrue();
});

it('hides navigation links the admin has no permission for', function () {
    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertDontSee(route('admin.roles.index'))
        ->assertDontSee(route('admin.settings.index'));
});
