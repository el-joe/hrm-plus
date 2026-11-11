<?php

use App\Models\LoginHistory;
use App\Models\User;

it('renders the admin login page', function () {
    $this->get('/admin/login')->assertOk()->assertSee(__('app.auth.admin_login_title'));
});

it('signs an admin in and records the successful login', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('User-Agent', 'PestBrowser/1.0')
        ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('employee');

    $this->assertDatabaseHas('login_histories', [
        'user_id' => $admin->id,
        'email' => 'admin@example.com',
        'area' => 'admin',
        'successful' => true,
        'ip_address' => '10.0.0.5',
        'user_agent' => 'PestBrowser/1.0',
    ]);

    expect($admin->fresh())
        ->last_login_at->not->toBeNull()
        ->last_login_ip->toBe('10.0.0.5');
});

it('rejects a wrong password and records the failed attempt', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('admin');
    $this->assertDatabaseHas('login_histories', [
        'user_id' => $admin->id,
        'successful' => false,
        'failure_reason' => 'invalid_credentials',
    ]);
});

it('records a failed attempt against an unknown email', function () {
    $this->post('/admin/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertDatabaseHas('login_histories', [
        'user_id' => null,
        'email' => 'nobody@example.com',
        'successful' => false,
    ]);
});

it('rejects an employee account on the admin login without revealing it exists', function () {
    User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/admin/login', ['email' => 'employee@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('admin');
    $this->assertDatabaseHas('login_histories', [
        'email' => 'employee@example.com',
        'area' => 'admin',
        'failure_reason' => 'account_type_not_allowed',
    ]);
});

it('rejects an admin whose account is not active', function (string $state) {
    User::factory()->admin()->{$state}()->create(['email' => 'admin@example.com']);

    $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);

    $this->assertGuest('admin');
})->with(['inactive', 'suspended']);

it('locks the login out after five failed attempts', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-password']);
    }

    $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('admin');
    expect(LoginHistory::query()->where('failure_reason', 'throttled')->count())->toBe(1);
});

it('signs an admin out', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'admin')
        ->post('/admin/logout')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});

it('redirects guests from the admin area to the admin login', function () {
    $this->get('/admin')->assertRedirect(route('admin.login'));
});

it('sends a signed-in admin away from the login page to the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create(), 'admin')
        ->get('/admin/login')
        ->assertRedirect(route('admin.dashboard'));
});
