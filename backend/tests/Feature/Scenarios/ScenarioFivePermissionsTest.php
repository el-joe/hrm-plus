<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogue;

/*
| Spec §12 end-to-end scenario 5 (Permissions), through the HTTP routes.
|
| The other four scenarios live next to their modules:
|   1 New employee      tests/Feature/Recruitment/RecruitmentWorkflowTest.php ("completes end-to-end scenario 1 …")
|   2 Mobile attendance tests/Feature/Attendance/MobileAttendanceTest.php ("runs scenario 2 …")
|   3 Manual attendance tests/Feature/Attendance/ManualAttendanceTest.php ("runs scenario 3 …")
|   4 Payroll           tests/Feature/Payroll/PayrollScenarioFourTest.php
| Every default role is exercised by tests/Feature/Scenarios/DefaultRolesSmokeTest.php.
*/

it('runs scenario 5: a Super Admin creates an attendance-only role and the backend enforces it everywhere', function () {
    $superAdmin = superAdmin();
    $employee = Employee::factory()->create(['basic_salary' => '9000.00']);
    $attendancePermissions = collect(app(PermissionCatalogue::class)->all())->filter(fn (string $name): bool => str_starts_with($name, 'attendance.'))->values()->all();

    // 1–2. The Super Admin creates the role and grants attendance permissions only.
    $this->actingAs($superAdmin, 'admin')
        ->post(route('admin.roles.store'), ['name' => 'Scenario Attendance Officer', 'description' => 'Attendance only', 'permissions' => $attendancePermissions])
        ->assertRedirect()->assertSessionHasNoErrors();
    $role = Role::query()->where('name', 'Scenario Attendance Officer')->sole();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(collect($attendancePermissions)->sort()->values()->all());

    $this->post(route('admin.admin-users.store'), [
        'name' => 'Officer One', 'email' => 'officer.one@example.com', 'password' => 'Attendance-2026', 'password_confirmation' => 'Attendance-2026',
        'status' => 'active', 'roles' => [$role->id],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('admin.logout'));
    auth('admin')->logout();

    // 3. The officer signs in.
    $this->post(route('admin.login'), ['email' => 'officer.one@example.com', 'password' => 'Attendance-2026'])
        ->assertRedirect(route('admin.dashboard'));
    $officer = User::query()->where('email', 'officer.one@example.com')->sole();
    $this->assertAuthenticatedAs($officer, 'admin');

    // 4. The officer manages attendance.
    $this->get(route('admin.attendance.daily'))->assertOk();
    $this->get(route('admin.attendance.bulk.create'))->assertOk();

    // 5. No payroll, no employee financial information.
    $this->get(route('admin.payroll-runs.index'))->assertForbidden();
    $this->get(route('admin.payslips.index'))->assertForbidden();
    $this->get(route('admin.employees.tab', [$employee, 'financial']))->assertForbidden();
    $this->get(route('admin.reports.show', 'payroll_summary'))->assertForbidden();
    expect($officer->can('employee_financials.view'))->toBeFalse()
        ->and($officer->can('payroll.view'))->toBeFalse();

    // 6. The backend rejects unauthorised API requests: no token, and an admin account cannot get one.
    $this->getJson('/api/v1/me/payslips')->assertUnauthorized();
    $apiLogin = $this->postJson('/api/v1/auth/login', ['email' => 'officer.one@example.com', 'password' => 'Attendance-2026', 'device_name' => 'test']);
    expect($apiLogin->status())->toBeIn([401, 403, 422])
        ->and($apiLogin->json('data.token'))->toBeNull();
});
