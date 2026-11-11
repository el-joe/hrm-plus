<?php

use App\Models\User;

it('renders the employee login page', function () {
    $this->get('/portal/login')->assertOk()->assertSee(__('app.auth.portal_login_title'));
});

it('signs an employee in to the portal and records the login', function () {
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/portal/login', ['email' => 'employee@example.com', 'password' => 'password'])
        ->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($employee, 'employee');
    $this->assertGuest('admin');
    $this->assertDatabaseHas('login_histories', ['user_id' => $employee->id, 'area' => 'portal', 'successful' => true]);
});

it('shows the portal home to a signed-in employee', function () {
    $employee = User::factory()->employee()->create(['name' => 'Sara Ali']);

    $this->actingAs($employee, 'employee')
        ->get('/portal')
        ->assertOk()
        ->assertSee('Sara Ali');
});

it('rejects a wrong password on the portal login', function () {
    User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/portal/login', ['email' => 'employee@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('employee');
});

it('rejects an inactive employee on the portal login', function () {
    User::factory()->employee()->inactive()->create(['email' => 'employee@example.com']);

    $this->post('/portal/login', ['email' => 'employee@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);

    $this->assertGuest('employee');
});

it('rejects an admin with no employee profile on the portal login', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post('/portal/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('employee');
});

it('lets an admin linked to an employee profile sign in to the portal', function () {
    $admin = User::factory()->admin()->linkedToEmployee()->create(['email' => 'admin@example.com']);

    $this->post('/portal/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($admin, 'employee');
});

it('redirects guests from the portal to the portal login', function () {
    $this->get('/portal')->assertRedirect(route('portal.login'));
});

it('signs an employee out', function () {
    $this->actingAs(User::factory()->employee()->create(), 'employee')
        ->post('/portal/logout')
        ->assertRedirect(route('portal.login'));

    $this->assertGuest('employee');
});
