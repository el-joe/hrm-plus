<?php

use App\Models\Employee;

/*
| Phase 8a module tabs of the admin employee profile (performance, training,
| assets, disciplinary) and their events in the history timeline.
*/

it('renders the history timeline of an employee without any module records', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(superAdmin(), 'admin')
        ->get(route('admin.employees.tab', [$employee, 'history']))
        ->assertOk();
});

it('opens every module tab for an admin holding the module permissions', function (string $tab) {
    $employee = Employee::factory()->create();

    $this->actingAs(superAdmin(), 'admin')
        ->get(route('admin.employees.tab', [$employee, $tab]))
        ->assertOk();
})->with(['performance', 'training', 'assets', 'disciplinary']);

it('refuses the disciplinary tab to an HR specialist (restricted permission)', function () {
    $employee = Employee::factory()->create();
    $specialist = adminWithRoles('HR Specialist');

    $this->actingAs($specialist, 'admin')
        ->get(route('admin.employees.tab', [$employee, 'disciplinary']))
        ->assertForbidden();

    $this->actingAs($specialist, 'admin')
        ->get(route('admin.employees.show', $employee))
        ->assertOk()
        ->assertDontSee(route('admin.employees.tab', [$employee, 'disciplinary']));
});
