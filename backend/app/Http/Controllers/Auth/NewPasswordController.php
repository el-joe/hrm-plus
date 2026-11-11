<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

abstract class NewPasswordController extends Controller
{
    abstract protected function area(): AuthArea;

    public function create(Request $request, string $token): View
    {
        return view($this->area()->viewDirectory().'.auth.reset-password', [
            'area' => $this->area(),
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $area = $this->area();
        $account = User::query()->where('email', $request->validated('email'))->first();

        $status = $account !== null && $area->admits($account)
            ? Password::reset(
                $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
                function (User $user, string $password) use ($audit): void {
                    $user->forceFill([
                        'password' => $password,
                        'password_changed_at' => now(),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // A reset password ends every mobile session of the account.
                    $user->tokens()->delete();

                    $audit->log('auth.password_reset', $user, actor: $user);

                    event(new PasswordReset($user));
                },
            )
            : Password::INVALID_USER;

        if ($status !== Password::PASSWORD_RESET) {
            // One message for every failure, so tokens and accounts cannot be probed.
            throw ValidationException::withMessages(['email' => __('passwords.token')]);
        }

        return redirect()->route($area->loginRoute())->with('status', __('passwords.reset'));
    }
}
