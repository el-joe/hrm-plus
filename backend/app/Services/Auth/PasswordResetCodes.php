<?php

namespace App\Services\Auth;

use App\Enums\AuthArea;
use App\Models\User;
use App\Notifications\Auth\PasswordResetCode;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One-time codes for "forgot password" in the mobile app (POST /api/v1/auth/password/*).
 *
 * A 6-digit code is e-mailed to an employee account that may use the API. Only an HMAC of the
 * code is kept (in the cache, keyed by a hash of the e-mail address), with its expiry
 * (setting security.password_reset_code_minutes) and a wrong-attempt counter: after
 * security.password_reset_code_attempts wrong codes the code is burnt. A new request replaces
 * the previous code. Unknown or non-employee e-mail addresses get the same answer and nothing
 * is sent, so accounts cannot be probed.
 */
class PasswordResetCodes
{
    public const LENGTH = 6;

    /**
     * Seconds before another code may be sent to the same address.
     */
    public const RESEND_SECONDS = 60;

    public function __construct(private readonly Cache $cache, private readonly AuditLogger $audit) {}

    /**
     * Sends a fresh code when the address belongs to an active account the app serves.
     * Returns true when a code was sent (callers must not reveal it).
     */
    public function send(string $email): bool
    {
        $user = $this->account($email);

        if ($user === null) {
            return false;
        }

        $state = $this->state($email);

        if ($state !== null && Carbon::parse($state['sent_at'])->addSeconds(self::RESEND_SECONDS)->isFuture()) {
            return false;
        }

        $code = $this->generate();
        $expiresAt = now()->addMinutes($this->minutes());

        $this->cache->put($this->key($email), [
            'user_id' => $user->id,
            'hash' => $this->hash($code),
            'expires_at' => $expiresAt->toIso8601String(),
            'sent_at' => now()->toIso8601String(),
            'attempts' => 0,
        ], $expiresAt);

        $user->notify((new PasswordResetCode($code, $this->minutes()))->locale($user->locale ?: (string) setting('localisation.default_locale')));
        $this->audit->log('auth.password_reset_code_sent', $user, meta: ['channel' => 'api'], actor: $user);

        return true;
    }

    /**
     * Checks a code without consuming it; a wrong code counts as an attempt.
     * Returns the expiry of a valid code, or null.
     */
    public function verify(string $email, string $code): ?Carbon
    {
        $state = $this->state($email);

        if ($state === null) {
            return null;
        }

        $expiresAt = Carbon::parse($state['expires_at']);

        if ($expiresAt->isPast()) {
            $this->forget($email);

            return null;
        }

        if (! hash_equals($state['hash'], $this->hash($code))) {
            $state['attempts']++;

            if ($state['attempts'] >= $this->maxAttempts()) {
                $this->forget($email);
            } else {
                $this->cache->put($this->key($email), $state, $expiresAt);
            }

            return null;
        }

        return $expiresAt;
    }

    /**
     * Verifies and consumes a code; returns the account it was issued for.
     */
    public function consume(string $email, string $code): ?User
    {
        if ($this->verify($email, $code) === null) {
            return null;
        }

        $userId = $this->state($email)['user_id'] ?? null;
        $this->forget($email);

        $user = $userId !== null ? User::query()->find($userId) : null;

        return $user !== null && $this->admits($user) && Str::lower($user->email) === Str::lower(trim($email)) ? $user : null;
    }

    public function forget(string $email): void
    {
        $this->cache->forget($this->key($email));
    }

    public function minutes(): int
    {
        return (int) setting('security.password_reset_code_minutes');
    }

    public function maxAttempts(): int
    {
        return (int) setting('security.password_reset_code_attempts');
    }

    private function account(string $email): ?User
    {
        $user = User::query()->where('email', trim($email))->first();

        return $user !== null && $this->admits($user) ? $user : null;
    }

    private function admits(User $user): bool
    {
        return AuthArea::Api->admits($user) && $user->isActive();
    }

    /**
     * @return array{user_id: int, hash: string, expires_at: string, sent_at: string, attempts: int}|null
     */
    private function state(string $email): ?array
    {
        $state = $this->cache->get($this->key($email));

        return is_array($state) ? $state : null;
    }

    private function generate(): string
    {
        return str_pad((string) random_int(0, (10 ** self::LENGTH) - 1), self::LENGTH, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', trim($code), (string) config('app.key'));
    }

    private function key(string $email): string
    {
        return 'password-reset-code:'.hash('sha256', Str::lower(trim($email)));
    }
}
