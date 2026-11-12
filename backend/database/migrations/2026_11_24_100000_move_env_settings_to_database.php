<?php

use App\Services\Settings\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * One-off data migration: the operational values that used to be read from .env are system settings now
 * (Settings → Security / Reports / Data retention). A variable still set in the environment (.env or the
 * server) with a valid, non-default value is copied into the settings table — unless an administrator
 * already stored that setting — so upgrading does not silently drop it; from then on the database owns it
 * and the variable can be deleted. Invalid values are skipped (and logged), never stored.
 *
 * Note: env() only sees .env while the configuration is not cached; run `php artisan migrate` before
 * `php artisan config:cache` (the documented deployment order) for the copy to happen.
 */
return new class extends Migration
{
    /**
     * Environment variable => [setting key, kind of value].
     *
     * @var array<string, array{0: string, 1: 'integer'|'boolean'|'list'|'same_site'|'secure_cookie'}>
     */
    private const VARIABLES = [
        'AUTH_LOGIN_MAX_ATTEMPTS' => ['security.login_max_attempts', 'integer'],
        'AUTH_LOGIN_DECAY_SECONDS' => ['security.login_lockout_seconds', 'integer'],
        'SANCTUM_TOKEN_EXPIRATION' => ['security.api_token_lifetime_minutes', 'integer'],
        'SESSION_SAME_SITE' => ['security.session_same_site', 'same_site'],
        'SESSION_SECURE_COOKIE' => ['security.session_secure_cookie', 'secure_cookie'],
        'SECURITY_HEADERS' => ['security.headers_enabled', 'boolean'],
        'SECURITY_CSP' => ['security.csp_enabled', 'boolean'],
        'SECURITY_HSTS_MAX_AGE' => ['security.hsts_max_age_seconds', 'integer'],
        'SECURITY_EXPORTS_PER_MINUTE' => ['security.exports_per_minute', 'integer'],
        'SECURITY_CSP_IMG_SRC' => ['security.csp_extra_img_src', 'list'],
        'SECURITY_CSP_CONNECT_SRC' => ['security.csp_extra_connect_src', 'list'],
        'REPORTS_QUEUE_THRESHOLD_XLSX' => ['reports.queue_threshold_xlsx', 'integer'],
        'REPORTS_QUEUE_THRESHOLD_CSV' => ['reports.queue_threshold_csv', 'integer'],
        'REPORTS_QUEUE_THRESHOLD_PDF' => ['reports.queue_threshold_pdf', 'integer'],
        'REPORTS_PDF_MAX_ROWS' => ['reports.pdf_max_rows', 'integer'],
        'LOG_DAILY_DAYS' => ['retention.log_days', 'integer'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = app(SettingsService::class);
        $settings->flush();
        $candidates = [];

        foreach (self::VARIABLES as $variable => [$key, $kind]) {
            $value = $this->normalise(env($variable), $kind);

            if ($value !== null && ! $settings->isStored($key) && $value !== $settings->defaultValue($key)) {
                [$group, $name] = explode('.', $key, 2);
                $candidates[$group][$name] = $value;
            }
        }

        $copied = [];

        foreach ($candidates as $group => $values) {
            foreach ($this->valid($settings, $group, $values) as $name => $value) {
                $copied[$group.'.'.$name] = $value;
            }
        }

        if ($copied !== []) {
            $settings->setMany($copied);
        }
    }

    public function down(): void
    {
        // The copied values stay: they are ordinary settings now.
    }

    private function normalise(mixed $raw, string $kind): mixed
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        return match ($kind) {
            'integer' => filter_var($raw, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'boolean' => is_bool($raw) ? $raw : filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'list' => array_values(array_filter(array_map(fn (string $origin): string => rtrim(trim($origin), '/'), explode(',', (string) $raw)))),
            'same_site' => strtolower((string) $raw),
            'secure_cookie' => match (is_bool($raw) ? $raw : filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
                true => 'always',
                false => 'never',
                null => null,
            },
        };
    }

    /**
     * The candidate values of one group that pass its settings form rules, together with the current values
     * (cross-field rules included); a candidate with an error is dropped and the rest validated again.
     *
     * @param  array<string, mixed>  $candidates
     * @return array<string, mixed>
     */
    private function valid(SettingsService $settings, string $group, array $candidates): array
    {
        $current = $settings->group($group);

        while ($candidates !== []) {
            $errors = Validator::make(array_merge($current, $candidates), $settings->rules($group))->errors();
            $invalid = array_filter(array_keys($candidates), fn (string $name): bool => $errors->has($name) || $errors->has($name.'.*'));

            if ($invalid === []) {
                break;
            }

            foreach ($invalid as $name) {
                Log::warning("Ignored the invalid environment value of setting {$group}.{$name} while moving .env values to system settings.");
                unset($candidates[$name]);
            }
        }

        return $candidates;
    }
};
