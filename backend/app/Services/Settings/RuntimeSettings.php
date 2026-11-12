<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Applies the settings that drive framework configuration (Phase 8b), once per boot:
 *
 * - security.session_lifetime_minutes → session.lifetime (admin and portal web sessions);
 * - the "mail" group → mail.default / mail.mailers.smtp / mail.from, only once an administrator saved the
 *   mail settings (until then .env decides, so tests and fresh installs keep their mailer).
 *
 * Login throttling and the password policy read their settings where they are used (AuthenticateUser,
 * Password::defaults()). Missing tables (fresh install, migrations running) leave the config untouched.
 */
class RuntimeSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    public function apply(): void
    {
        try {
            config(['session.lifetime' => (int) $this->settings->get('security.session_lifetime_minutes')]);

            if ($this->settings->isStored('mail.mailer')) {
                $this->applyMail();
            }
        } catch (Throwable) {
            // Settings table / cache not available yet: keep the file configuration.
        }
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
