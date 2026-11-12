<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\RuntimeSettings;
use App\Services\Settings\SettingsService;
use Laravel\Sanctum\PersonalAccessToken;

/*
| Operational values that used to be read from .env (sign-in lockout, mobile token lifetime, session cookie,
| security headers / CSP / HSTS, export limit, report thresholds, log days) are system settings: each one
| changes runtime behaviour, is validated on its settings screen, survives a missing settings store and is
| copied from a still-set environment variable by the one-off data migration.
*/

beforeEach(fn () => seedRoles());

/**
 * The current values of a settings group merged with overrides, as the settings form would post them.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsForm(string $group, array $overrides = []): array
{
    $values = app(SettingsService::class)->group($group);

    foreach ($values as $name => $value) {
        if (is_array($value)) {
            $values[$name] = implode("\n", $value);
        } elseif (is_bool($value)) {
            $values[$name] = $value ? '1' : '0';
        }
    }

    return array_merge($values, $overrides);
}

describe('runtime', function () {
    it('locks the mobile login out after the configured number of failures', function () {
        app(SettingsService::class)->set('security.login_max_attempts', 3);
        User::factory()->employee()->create(['email' => 'employee@example.com']);
        $login = ['email' => 'employee@example.com', 'password' => 'wrong', 'device_name' => 'Pixel 9'];

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/v1/auth/login', $login)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [...$login, 'password' => 'password'])->assertTooManyRequests();
    });

    it('issues mobile tokens with the configured lifetime and applies it to Sanctum', function () {
        $this->freezeSecond();
        app(SettingsService::class)->set('security.api_token_lifetime_minutes', 90);
        app(RuntimeSettings::class)->apply();
        User::factory()->employee()->create(['email' => 'employee@example.com']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'employee@example.com', 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(90)->toIso8601String());

        expect(PersonalAccessToken::findToken($response->json('data.token'))->expires_at->equalTo(now()->addMinutes(90)))->toBeTrue()
            ->and(config('sanctum.expiration'))->toBe(90);

        $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('data.auth.token_lifetime_minutes', 90);
    });

    it('builds the session cookie with the configured SameSite and Secure attributes', function () {
        $settings = app(SettingsService::class);
        $settings->setMany(['security.session_same_site' => 'strict', 'security.session_secure_cookie' => 'always']);
        app(RuntimeSettings::class)->apply();

        $cookie = collect($this->get(route('admin.login'))->headers->getCookies())->firstWhere(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));
        expect($cookie->getSameSite())->toBe('strict')->and($cookie->isSecure())->toBeTrue();

        // "none" is only accepted by browsers on a Secure cookie, whatever the Secure setting says.
        $settings->setMany(['security.session_same_site' => 'none', 'security.session_secure_cookie' => 'never']);
        app(RuntimeSettings::class)->apply();
        expect(config('session.same_site'))->toBe('none')->and(config('session.secure'))->toBeTrue();

        // "auto" follows the scheme of APP_URL.
        $settings->setMany(['security.session_same_site' => 'lax', 'security.session_secure_cookie' => 'auto']);
        config(['app.url' => 'http://hrm.test']);
        app(RuntimeSettings::class)->apply();
        expect(config('session.secure'))->toBeFalse();
        config(['app.url' => 'https://hrm.test']);
        app(RuntimeSettings::class)->apply();
        expect(config('session.secure'))->toBeTrue();
    });

    it('switches the security headers and the content security policy off and on', function () {
        $settings = app(SettingsService::class);

        $settings->set('security.csp_enabled', false);
        $this->get(route('admin.login'))->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeaderMissing('Content-Security-Policy');

        $settings->setMany(['security.csp_enabled' => true, 'security.headers_enabled' => false]);
        $this->get(route('admin.login'))->assertHeaderMissing('X-Frame-Options')->assertHeaderMissing('Content-Security-Policy');
    });

    it('adds the extra CSP origins and sends the configured HSTS max-age', function () {
        app(SettingsService::class)->setMany([
            'security.csp_extra_img_src' => ['https://cdn.example.com'],
            'security.csp_extra_connect_src' => ['https://api.example.com'],
            'security.hsts_max_age_seconds' => 600,
        ]);
        $https = str_replace('http://', 'https://', route('admin.login'));

        $policy = (string) $this->get($https)->assertHeader('Strict-Transport-Security', 'max-age=600; includeSubDomains')
            ->headers->get('Content-Security-Policy');

        expect($policy)->toContain("img-src 'self' data: blob: https://cdn.example.com")
            ->toContain("connect-src 'self' https://api.example.com");

        app(SettingsService::class)->set('security.hsts_max_age_seconds', 0);
        $this->get($https)->assertHeaderMissing('Strict-Transport-Security');
    });

    it('applies the daily log retention to the log channel', function () {
        app(SettingsService::class)->set('retention.log_days', 30);
        app(RuntimeSettings::class)->apply();

        expect(config('logging.channels.daily.max_files'))->toBe(30);
    });

    it('falls back to the registry defaults when the settings store cannot be read', function () {
        config(['sanctum.expiration' => 5, 'session.same_site' => 'strict', 'logging.channels.daily.max_files' => 99]);
        $broken = Mockery::mock(SettingsService::class)->makePartial();
        $broken->shouldReceive('get')->andThrow(new RuntimeException('settings table missing'));
        app()->instance(SettingsService::class, $broken);

        (new RuntimeSettings($broken))->apply();

        expect(config('sanctum.expiration'))->toBe(43200)
            ->and(config('session.same_site'))->toBe('lax')
            ->and(config('logging.channels.daily.max_files'))->toBe(14);

        // The security headers keep their defaults too.
        $this->get(route('admin.login'))->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Content-Security-Policy');
    });
});

describe('settings form', function () {
    it('shows the moved settings with their help text', function () {
        $this->actingAs(adminWithPermissions('settings.view'), 'admin')->get(route('admin.settings.edit', 'security'))->assertOk()
            ->assertSee(__('settings.keys.security.api_token_lifetime_minutes'))
            ->assertSee(__('settings.help.security.session_secure_cookie'))
            ->assertSee('name="csp_extra_img_src"', false);

        $this->actingAs(adminWithPermissions('settings.view'), 'admin')->get(route('admin.settings.edit', 'reports'))->assertOk()
            ->assertSee(__('settings.keys.reports.pdf_max_rows'));
    });

    it('saves CSP origins typed one per line, audited', function () {
        $this->actingAs(superAdmin(), 'admin')
            ->put(route('admin.settings.update', 'security'), settingsForm('security', [
                'csp_extra_img_src' => "https://cdn.example.com/\nhttps://*.files.example.com:8443",
                'exports_per_minute' => '5',
            ]))
            ->assertSessionHasNoErrors();

        expect(setting('security.csp_extra_img_src'))->toBe(['https://cdn.example.com', 'https://*.files.example.com:8443'])
            ->and(setting('security.exports_per_minute'))->toBe(5)
            ->and(AuditLog::query()->where('auditable_type', (new Setting)->getMorphClass())->exists())->toBeTrue();
    });

    it('rejects invalid values of the moved settings', function (string $group, array $overrides, string $error) {
        $this->actingAs(superAdmin(), 'admin')
            ->put(route('admin.settings.update', $group), settingsForm($group, $overrides))
            ->assertSessionHasErrors($error);
    })->with([
        'origin with a path' => ['security', ['csp_extra_connect_src' => 'https://api.example.com/v1'], 'csp_extra_connect_src.0'],
        'not an origin' => ['security', ['csp_extra_img_src' => 'javascript:alert(1)'], 'csp_extra_img_src.0'],
        'unknown SameSite' => ['security', ['session_same_site' => 'loose'], 'session_same_site'],
        'unknown Secure mode' => ['security', ['session_secure_cookie' => 'sometimes'], 'session_secure_cookie'],
        'token lifetime too short' => ['security', ['api_token_lifetime_minutes' => '1'], 'api_token_lifetime_minutes'],
        'no exports at all' => ['security', ['exports_per_minute' => '0'], 'exports_per_minute'],
        'HSTS beyond two years' => ['security', ['hsts_max_age_seconds' => '99999999'], 'hsts_max_age_seconds'],
        'PDF limit below the PDF queue threshold' => ['reports', ['queue_threshold_pdf' => '500', 'pdf_max_rows' => '400'], 'pdf_max_rows'],
        'negative threshold' => ['reports', ['queue_threshold_csv' => '-1'], 'queue_threshold_csv'],
        'log days out of range' => ['retention', ['log_days' => '0'], 'log_days'],
    ]);

    it('keeps the moved settings read-only for a settings viewer', function () {
        $this->actingAs(adminWithPermissions('settings.view'), 'admin')
            ->put(route('admin.settings.update', 'reports'), settingsForm('reports', ['pdf_max_rows' => '500']))
            ->assertForbidden();

        expect(setting('reports.pdf_max_rows'))->toBe(3000);
    });
});

describe('data migration', function () {
    /**
     * @param  array<string, string>  $variables
     */
    function runEnvMigration(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }

        try {
            (require database_path('migrations/2026_11_24_100000_move_env_settings_to_database.php'))->up();
        } finally {
            foreach (array_keys($variables) as $name) {
                unset($_SERVER[$name], $_ENV[$name]);
            }
        }
    }

    it('copies still-set environment values into unset settings', function () {
        app(SettingsService::class)->set('security.exports_per_minute', 7);

        runEnvMigration([
            'AUTH_LOGIN_MAX_ATTEMPTS' => '8',
            'SESSION_SECURE_COOKIE' => 'false',
            'SECURITY_CSP' => 'false',
            'SECURITY_CSP_IMG_SRC' => 'https://cdn.example.com, https://img.example.com/',
            'SECURITY_EXPORTS_PER_MINUTE' => '50',
            'REPORTS_PDF_MAX_ROWS' => '6000',
            'REPORTS_QUEUE_THRESHOLD_PDF' => '800',
            'LOG_DAILY_DAYS' => '30',
            // Default values and invalid values are not stored.
            'SANCTUM_TOKEN_EXPIRATION' => '43200',
            'SECURITY_HSTS_MAX_AGE' => 'forever',
            'SESSION_SAME_SITE' => 'loose',
        ]);

        $settings = app(SettingsService::class);
        expect($settings->get('security.login_max_attempts'))->toBe(8)
            ->and($settings->get('security.session_secure_cookie'))->toBe('never')
            ->and($settings->get('security.csp_enabled'))->toBeFalse()
            ->and($settings->get('security.csp_extra_img_src'))->toBe(['https://cdn.example.com', 'https://img.example.com'])
            ->and($settings->get('security.exports_per_minute'))->toBe(7)
            ->and($settings->get('reports.pdf_max_rows'))->toBe(6000)
            ->and($settings->get('reports.queue_threshold_pdf'))->toBe(800)
            ->and($settings->get('retention.log_days'))->toBe(30)
            ->and($settings->isStored('security.api_token_lifetime_minutes'))->toBeFalse()
            ->and($settings->isStored('security.hsts_max_age_seconds'))->toBeFalse()
            ->and($settings->isStored('security.session_same_site'))->toBeFalse();
    });

    it('drops values that only fail together with the current settings', function () {
        // A PDF queue threshold above the (default) PDF limit of 3000 would make every large PDF unqueueable.
        runEnvMigration(['REPORTS_QUEUE_THRESHOLD_PDF' => '9000']);

        expect(app(SettingsService::class)->isStored('reports.queue_threshold_pdf'))->toBeFalse();
    });
});
