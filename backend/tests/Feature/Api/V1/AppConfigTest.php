<?php

use App\Services\Settings\SettingsService;

/*
| GET /api/v1/app/config (Phase 10): public configuration of the mobile app's splash screen.
*/

it('answers without a token with the company, languages, formats, features and versions', function () {
    $settings = app(SettingsService::class);
    $settings->set('mobile_app.min_version', '1.2.0');
    $settings->set('mobile_app.latest_version', '1.4.1');
    $settings->set('localisation.use_arabic_digits', true);
    $settings->set('attendance.face_verification_required', true);

    $this->getJson(route('api.v1.app.config'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.app.min_version', '1.2.0')
        ->assertJsonPath('data.app.latest_version', '1.4.1')
        ->assertJsonPath('data.localisation.use_arabic_digits', true)
        ->assertJsonPath('data.features.face_verification_required_by_default', true)
        ->assertJsonPath('data.features.password_reset_otp', true)
        ->assertJsonPath('data.auth.otp_length', 6)
        ->assertJsonPath('data.locales', [
            ['code' => 'ar', 'dir' => 'rtl', 'name' => 'العربية'],
            ['code' => 'en', 'dir' => 'ltr', 'name' => 'English'],
        ])
        ->assertJsonStructure(['data' => [
            'company' => ['name', 'name_translations' => ['ar', 'en'], 'logo_url'],
            'default_locale',
            'localisation' => ['timezone', 'date_format', 'time_format', 'use_arabic_digits', 'currency_code', 'currency_decimals', 'first_day_of_week'],
            'features' => ['face_provider', 'push_enabled', 'loan_requests', 'hr_requests', 'training_requests', 'employee_goals'],
            'auth' => ['otp_ttl_seconds', 'otp_resend_seconds', 'password_min_length'],
            'app' => ['android_store_url', 'ios_store_url'],
            'server_time',
        ]]);
});

it('never exposes secrets', function () {
    app(SettingsService::class)->set('mail.password', 'smtp-secret-value');

    $this->getJson(route('api.v1.app.config'))->assertOk()->assertDontSee('smtp-secret-value');
});

it('validates app versions on the settings screen', function () {
    $this->actingAs(superAdmin(), 'admin')
        ->put(route('admin.settings.update', 'mobile_app'), ['min_version' => 'one', 'latest_version' => '1.0.0'])
        ->assertSessionHasErrors('min_version');
});
