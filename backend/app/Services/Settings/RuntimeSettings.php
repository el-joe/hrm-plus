<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Applies the settings that drive framework configuration, once per boot (web, console and queue workers):
 *
 * - security.session_lifetime_minutes → session.lifetime (admin and portal web sessions);
 * - security.session_same_site / session_secure_cookie → session.same_site / session.secure ("auto" =
 *   Secure when APP_URL is https://; SameSite "none" always forces Secure, browsers drop it otherwise);
 * - security.api_token_lifetime_minutes → sanctum.expiration (mobile app bearer tokens);
 * - retention.log_days → logging.channels.daily.max_files;
 * - the "mail" group → mail.default / mail.mailers.smtp / mail.from, only once an administrator saved the
 *   mail settings (until then .env decides, so tests and fresh installs keep their mailer).
 *
 * It runs from AppServiceProvider::boot(), i.e. before any route middleware (StartSession builds the cookie
 * from this configuration). Login throttling, the password policy, security headers, the export limiter and
 * report thresholds read their settings where they are used.
 *
 * When the settings cannot be read (fresh install, migrations running, database down) the constant defaults
 * of config/settings.php are applied instead. Skipped while `config:cache` / `optimize` build the cached
 * configuration, so database values (and the SMTP password) never end up in bootstrap/cache/config.php.
 */
class RuntimeSettings
{
    /**
     * Framework config key => setting key.
     */
    private const MAPPED = [
        'session.lifetime' => 'security.session_lifetime_minutes',
        'sanctum.expiration' => 'security.api_token_lifetime_minutes',
        'logging.channels.daily.max_files' => 'retention.log_days',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function apply(): void
    {
        if (app()->runningConsoleCommand('config:cache', 'optimize')) {
            return;
        }

        try {
            $this->applyFramework(fn (string $key): mixed => $this->settings->get($key));

            if ($this->settings->isStored('mail.mailer')) {
                $this->applyMail();
            }
        } catch (Throwable) {
            // Settings table / cache / database not available: fall back to the registry defaults.
            $this->applyFramework(fn (string $key): mixed => $this->settings->defaultValue($key));
        }
    }

    /**
     * @param  callable(string): mixed  $value
     */
    private function applyFramework(callable $value): void
    {
        $values = [];

        foreach (self::MAPPED as $config => $setting) {
            $values[$config] = (int) $value($setting);
        }

        $sameSite = (string) $value('security.session_same_site');
        $values['session.same_site'] = $sameSite;
        $values['session.secure'] = $sameSite === 'none' || match ((string) $value('security.session_secure_cookie')) {
            'always' => true,
            'never' => false,
            default => str_starts_with((string) config('app.url'), 'https://'),
        };

        config($values);
    }

    public function applyMail(): void
    {
        $mailer = (string) $this->settings->get('mail.mailer');

        config([
            'mail.default' => $mailer,
            'mail.from.address' => (string) $this->settings->get('mail.from_address'),
            'mail.from.name' => (string) $this->settings->get('mail.from_name'),
        ]);

        if ($mailer === 'smtp') {
            config([
                'mail.mailers.smtp.scheme' => $this->settings->get('mail.encryption') === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.url' => null,
                'mail.mailers.smtp.host' => (string) $this->settings->get('mail.host'),
                'mail.mailers.smtp.port' => (int) $this->settings->get('mail.port'),
                'mail.mailers.smtp.username' => $this->settings->get('mail.username') ?: null,
                'mail.mailers.smtp.password' => $this->settings->get('mail.password'),
            ]);
        }

        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        Mail::clearResolvedInstance('mail.manager');
    }
}
