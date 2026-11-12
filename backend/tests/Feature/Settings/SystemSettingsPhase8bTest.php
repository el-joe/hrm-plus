<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Settings\RuntimeSettings;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

/*
| System settings screens added in Phase 8b (spec module 18): e-mail (encrypted password, test send),
| push (FCM key upload, simulated status, test push), security (password policy, session lifetime, login
| throttling), retention, HR requests, announcements. Secrets are encrypted, never echoed, redacted in audit.
*/

beforeEach(fn () => seedRoles());

it('opens every settings group for a settings viewer', function (string $group) {
    $this->actingAs(adminWithPermissions('settings.view'), 'admin')->get(route('admin.settings.edit', $group))->assertOk();
})->with(['mail', 'push', 'security', 'retention', 'hr_requests', 'announcements', 'notifications']);

it('stores the SMTP password encrypted, never shows it and redacts it from the audit log', function () {
    $admin = superAdmin();
    $payload = ['mailer' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer',
        'password' => 'S3cret-Pa55', 'from_address' => 'hr@example.test', 'from_name' => 'HR'];

    $this->actingAs($admin, 'admin')->put(route('admin.settings.update', 'mail'), $payload)->assertRedirect();

    $raw = Setting::query()->where('key', 'mail.password')->value('value');
    expect($raw)->not->toContain('S3cret')
        ->and(app(SettingsService::class)->get('mail.password'))->toBe('S3cret-Pa55')
        ->and(AuditLog::query()->where('auditable_type', 'setting')->get()->toJson())->not->toContain('S3cret');

    $this->actingAs($admin, 'admin')->get(route('admin.settings.edit', 'mail'))->assertOk()->assertDontSee('S3cret-Pa55');
});

it('keeps a stored secret when the field is left empty and clears it on request', function () {
    $admin = superAdmin();
    $settings = app(SettingsService::class);
    $settings->set('mail.password', 'keep-me');
    $base = ['mailer' => 'log', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'from_address' => 'hr@example.test', 'from_name' => 'HR'];

    $this->actingAs($admin, 'admin')->put(route('admin.settings.update', 'mail'), [...$base, 'password' => ''])->assertRedirect();
    $settings->flush();
    expect($settings->get('mail.password'))->toBe('keep-me');

    $this->actingAs($admin, 'admin')->put(route('admin.settings.update', 'mail'), [...$base, 'password' => '', 'password_clear' => '1'])->assertRedirect();
    $settings->flush();
    expect($settings->get('mail.password'))->toBeNull();
});

it('requires the SMTP host when the mailer is SMTP', function () {
    $this->actingAs(superAdmin(), 'admin')->put(route('admin.settings.update', 'mail'), [
        'mailer' => 'smtp', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'hr@example.test', 'from_name' => 'HR',
    ])->assertSessionHasErrors('host');
});

it('sends a test e-mail through the log mailer and audits it', function () {
    app(SettingsService::class)->setMany(['mail.mailer' => 'log']);

    $this->actingAs(superAdmin(), 'admin')->post(route('admin.settings.mail.test'), ['to' => 'me@example.test'])
        ->assertRedirect()->assertSessionHas('status', __('notifications.settings.mail_test_logged'));

    expect(AuditLog::query()->where('event', 'settings.mail_test')->exists())->toBeTrue();
});

it('applies the saved mail settings and the session lifetime at runtime', function () {
    app(SettingsService::class)->setMany([
        'mail.mailer' => 'smtp', 'mail.host' => 'smtp.example.test', 'mail.port' => 465, 'mail.encryption' => 'ssl', 'mail.password' => 'pw',
        'security.session_lifetime_minutes' => 45,
    ]);

    app(RuntimeSettings::class)->apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.password'))->toBe('pw')
        ->and(config('session.lifetime'))->toBe(45);
});

it('uploads a valid FCM service-account key, never shows it, and refuses other files', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $json = json_encode(['type' => 'service_account', 'project_id' => 'demo-hrm', 'client_email' => 'push@demo-hrm.iam.gserviceaccount.com', 'private_key' => $pem]);
    $admin = superAdmin();

    $this->actingAs($admin, 'admin')->post(route('admin.settings.push.credentials'), [
        'credentials' => UploadedFile::fake()->createWithContent('bad.json', '{"type":"x"}'),
    ])->assertSessionHasErrors('credentials');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.push.credentials'), [
        'credentials' => UploadedFile::fake()->createWithContent('key.json', $json),
    ])->assertSessionHasNoErrors();

    expect(Setting::query()->where('key', 'push.fcm_service_account')->value('value'))->not->toContain('PRIVATE KEY');

    $this->actingAs($admin, 'admin')->get(route('admin.settings.edit', 'push'))->assertOk()
        ->assertDontSee('PRIVATE KEY')->assertSee('demo-hrm');
});

it('labels push as simulated until FCM is configured', function () {
    $this->actingAs(superAdmin(), 'admin')->get(route('admin.settings.edit', 'push'))->assertOk()->assertSee(__('devices.simulated.badge'));
});

it('keeps the mail and push tools for settings.manage', function () {
    $viewer = adminWithPermissions('settings.view');

    $this->actingAs($viewer, 'admin')->post(route('admin.settings.mail.test'), ['to' => 'me@example.test'])->assertForbidden();
    $this->actingAs($viewer, 'admin')->post(route('admin.settings.push.test'), ['user_id' => 1])->assertForbidden();
    $this->actingAs($viewer, 'admin')->delete(route('admin.settings.push.credentials.destroy'))->assertForbidden();
});

it('applies the password policy and the login throttling settings', function () {
    $settings = app(SettingsService::class);
    $settings->setMany(['security.password_require_symbols' => true, 'security.login_max_attempts' => 3]);

    expect(validator(['p' => 'abcdefgh1'], ['p' => Password::defaults()])->fails())->toBeTrue()
        ->and(validator(['p' => 'abcdefgh1!'], ['p' => Password::defaults()])->fails())->toBeFalse();

    foreach (range(1, 3) as $attempt) {
        $this->post(route('admin.login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong']);
    }

    expect(RateLimiter::tooManyAttempts('login|nobody@example.test|127.0.0.1', 3))->toBeTrue();
    $this->post(route('admin.login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
});
