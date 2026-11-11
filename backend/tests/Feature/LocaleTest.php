<?php

use App\Models\User;
use App\Services\Settings\SettingsService;

it('renders in Arabic, right to left, by default', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee('دخول الإدارة');
});

it('switches a guest to English for the rest of the session', function () {
    $this->post('/locale', ['locale' => 'en'])->assertRedirect();

    $this->get('/admin/login')
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee('Admin sign in');
});

it('saves the chosen language on the signed-in account', function () {
    $admin = User::factory()->admin()->create(['locale' => null]);

    $this->actingAs($admin, 'admin')->post('/locale', ['locale' => 'en'])->assertRedirect();

    expect($admin->fresh()->locale)->toBe('en');
});

it('uses the saved language of the signed-in user over the default', function () {
    $employee = User::factory()->employee()->create(['locale' => 'en']);

    $this->actingAs($employee, 'employee')
        ->get('/portal')
        ->assertSee('lang="en"', false);
});

it('rejects an unsupported language', function () {
    $this->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

it('answers API errors in the language of the Accept-Language header', function () {
    $this->getJson('/api/v1/auth/me', ['Accept-Language' => 'en'])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');

    $this->getJson('/api/v1/auth/me', ['Accept-Language' => 'ar'])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'يجب تسجيل الدخول أولاً.');
});

it('falls back to the default language chosen in the system settings', function () {
    app(SettingsService::class)->set('localisation.default_locale', 'en');

    $this->get('/admin/login')
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false);
});

it('answers the API in the token owner\'s saved language when the app sends no Accept-Language', function () {
    $employee = User::factory()->employee()->create(['locale' => 'en']);

    $this->withToken($employee->createToken('phone', ['employee'])->plainTextToken)
        ->getJson('/api/v1/auth/login-history?sort=email', ['Accept-Language' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The given data was invalid.');
});
