<?php

use App\Models\Role;
use App\Models\User;

dataset('locales', [
    'Arabic' => ['ar', 'rtl'],
    'English' => ['en', 'ltr'],
]);

it('renders the admin login page with the theme bootstrap in both directions', function (string $locale, string $direction) {
    $this->withSession(['locale' => $locale])
        ->get('/admin/login')
        ->assertOk()
        ->assertSee('dir="'.$direction.'"', false)
        ->assertSee('id="app-i18n"', false)
        ->assertSee("localStorage.getItem('hrm-theme')", false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false);
})->with('locales');

it('renders every admin screen of a Super Admin in both directions', function (string $locale, string $direction) {
    $admin = superAdmin();
    $admin->update(['locale' => $locale]);
    $other = User::factory()->admin()->create();
    $role = Role::create(['name' => 'Custom test role', 'guard_name' => 'admin']);

    foreach ([
        '/admin',
        '/admin/admin-users',
        '/admin/admin-users/create',
        "/admin/admin-users/{$other->id}",
        "/admin/admin-users/{$other->id}/edit",
        "/admin/admin-users/{$other->id}/login-history",
        '/admin/roles',
        '/admin/roles/create',
        "/admin/roles/{$role->id}/edit",
        '/admin/audit-logs',
        '/admin/settings/localisation',
        '/admin/ui-kit',
    ] as $url) {
        $this->actingAs($admin->fresh(), 'admin')
            ->get($url)
            ->assertOk()
            ->assertSee('dir="'.$direction.'"', false)
            ->assertSee('data-admin-sidebar', false)
            ->assertSee('id="confirm-dialog"', false);
    }
})->with('locales');

it('renders the employee portal and its login in both directions', function (string $locale, string $direction) {
    $this->withSession(['locale' => $locale])->get('/portal/login')->assertOk()->assertSee('dir="'.$direction.'"', false);

    $employee = User::factory()->employee()->create(['locale' => $locale]);

    $this->actingAs($employee, 'employee')
        ->get('/portal')
        ->assertOk()
        ->assertSee('dir="'.$direction.'"', false)
        ->assertSee('data-region="navigation"', false);
})->with('locales');

it('shows a flashed status message as a toast', function () {
    $this->actingAs(superAdmin(), 'admin')
        ->withSession(['status' => 'Role saved for the test.'])
        ->get('/admin/roles')
        ->assertOk()
        ->assertSee('data-toast', false)
        ->assertSee('Role saved for the test.');
});
