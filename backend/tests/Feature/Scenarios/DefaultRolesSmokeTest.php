<?php

use App\Models\User;

/*
| Phase 11: each of the 11 default roles signs in through the login form, gets its dashboard,
| sees the sidebar link of its own module, does not see a link it may not use, and is refused
| (403) on that route.
*/

it('signs in as each default role and gets its dashboard, sidebar and refusals', function (string $role, string $visible, ?string $forbidden) {
    seedRoles();
    $admin = User::factory()->admin()->withRoles($role)->create(['email' => 'role.smoke@example.com']);

    $this->post(route('admin.login'), ['email' => 'role.smoke@example.com', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');

    $dashboard = $this->get(route('admin.dashboard'))->assertOk()
        ->assertSee('href="'.route($visible).'"', false);

    $this->get(route($visible))->assertOk();

    if ($forbidden !== null) {
        $dashboard->assertDontSee('href="'.route($forbidden).'"', false);
        $this->get(route($forbidden))->assertForbidden();
    }
})->with([
    'Super Admin' => ['Super Admin', 'admin.roles.index', null],
    'HR Manager' => ['HR Manager', 'admin.employees.index', 'admin.roles.index'],
    'HR Specialist' => ['HR Specialist', 'admin.employees.index', 'admin.payroll-runs.index'],
    'Recruiter' => ['Recruiter', 'admin.vacancies.index', 'admin.attendance.daily'],
    'Payroll Accountant' => ['Payroll Accountant', 'admin.payroll-runs.index', 'admin.attendance-devices.index'],
    'Attendance Officer' => ['Attendance Officer', 'admin.attendance.daily', 'admin.payroll-runs.index'],
    'Branch Manager' => ['Branch Manager', 'admin.employees.index', 'admin.payroll-runs.index'],
    'Department Manager' => ['Department Manager', 'admin.attendance.daily', 'admin.payroll-runs.index'],
    'Direct Manager' => ['Direct Manager', 'admin.leave-requests.index', 'admin.roles.index'],
    'Finance Officer' => ['Finance Officer', 'admin.payroll-runs.index', 'admin.vacancies.index'],
    'Reports Viewer' => ['Reports Viewer', 'admin.reports.index', 'admin.employees.index'],
]);

test('the smoke test covers every default role', function () {
    expect(array_keys(config('permissions.roles')))->toHaveCount(11);
});
