<?php

namespace App\Actions\Auth;

use App\Enums\AuthArea;
use App\Models\User;
use App\Services\Auth\LoginRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Verifies credentials for one sign-in area (admin, portal or API), applying
 * throttling, the account-type rule and the account-status rule, and writing
 * the login history. It does not log anyone in: the caller starts the
 * session or issues the token.
 *
 * MFA seam: a second factor belongs right after this action succeeds and
 * before the caller starts the session / issues the token.
 */
class AuthenticateUser
{
    public function __construct(private readonly LoginRecorder $recorder) {}

    /**
     * @throws ValidationException wrong credentials, inactive account, or (web) too many attempts
     * @throws AuthorizationException (API only) valid credentials for an account the API does not serve
     * @throws TooManyRequestsHttpException (API only) too many attempts
     */
    public function handle(AuthArea $area, string $email, string $password, Request $request): User
    {
        $throttleKey = $this->throttleKey($email, $request);

        $this->ensureIsNotRateLimited($area, $throttleKey, $email, $request);

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->fail($area, $throttleKey, $email, $request, 'invalid_credentials', $user);
        }

        if (! $area->admits($user)) {
            if ($area === AuthArea::Api) {
                $this->recorder->failure($email, $area, $request, 'account_type_not_allowed', $user);

                throw new AuthorizationException(__('auth.employee_only'));
            }

            // Same message as a wrong password: the form must not reveal that
            // the account exists in the other area.
            $this->fail($area, $throttleKey, $email, $request, 'account_type_not_allowed', $user);
        }

        if (! $user->isActive()) {
            $this->recorder->failure($email, $area, $request, 'account_'.$user->status->value, $user);

            throw ValidationException::withMessages(['email' => __('auth.inactive')]);
        }

        RateLimiter::clear($throttleKey);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        $this->recorder->success($user, $area, $request);

        return $user;
    }

    private function fail(AuthArea $area, string $throttleKey, string $email, Request $request, string $reason, ?User $user): never
    {
        RateLimiter::hit($throttleKey, (int) setting('security.login_lockout_seconds'));

        $this->recorder->failure($email, $area, $request, $reason, $user);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    private function ensureIsNotRateLimited(AuthArea $area, string $throttleKey, string $email, Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, (int) setting('security.login_max_attempts'))) {
            return;
        }

        $seconds = RateLimiter::availableIn($throttleKey);
        $message = __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]);

        $this->recorder->failure($email, $area, $request, 'throttled');

        if ($area === AuthArea::Api) {
            throw new TooManyRequestsHttpException($seconds, $message);
        }

        throw ValidationException::withMessages(['email' => $message]);
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'login|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }
}
