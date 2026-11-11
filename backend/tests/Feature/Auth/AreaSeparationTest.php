<?php

use App\Enums\UserStatus;
use App\Models\User;

it('never lets a signed-in employee into the admin area', function (string $uri) {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee, 'employee')
        ->get($uri)
        ->assertRedirect(route('admin.login'));
})->with(['/admin', '/admin/admin-users', '/admin/roles', '/admin/audit-logs', '/admin/settings']);

it('throws an employee out even when their session is on the admin guard', function () {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee, 'admin')
        ->get('/admin')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});

it('lets an admin linked to an employee profile open the portal from the admin session', function () {
    $admin = User::factory()->admin()->linkedToEmployee()->create();

    $this->actingAs($admin, 'admin')
        ->get('/portal')
        ->assertOk()
        ->assertSee(__('app.dashboard.signed_in_as_admin'))
        ->assertSee('77');
});

it('redirects an admin with no employee profile from the portal to the portal login', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'admin')
        ->get('/portal')
        ->assertRedirect(route('portal.login'));
});

it('ends the admin session of an account that was deactivated meanwhile', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();

    $admin->update(['status' => UserStatus::Suspended]);

    $this->get('/admin')->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
});

it('ends the portal session of an employee that was deactivated meanwhile', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee, 'employee')->get('/portal')->assertOk();

    $employee->update(['status' => UserStatus::Inactive]);

    $this->get('/portal')->assertRedirect(route('portal.login'));
    $this->assertGuest('employee');
});
