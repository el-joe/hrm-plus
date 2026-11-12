<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Auth\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Services\Auth\AccountSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal "Account settings" (spec section 5): password, language, theme, notification settings
 * link, where the account is signed in (browsers and app devices, each revocable) and the
 * recent sign-ins. Always the signed-in account; ids of other accounts' sessions are a 404.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountSessions $sessions) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('employee.account', [
            'user' => $user,
            'webSessions' => $this->sessions->webSessions($user, $request->session()->getId()),
            'tracksWebSessions' => AccountSessions::usesDatabaseSessions(),
            'tokens' => $this->sessions->tokens($user),
            'logins' => $user->loginHistories()->latest('id')->limit(10)->get(),
        ]);
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePassword $change): RedirectResponse
    {
        $change->handle($request->user(), $request->validated('password'), keepSessionId: $request->session()->getId());
        // The password hash changed: keep this browser signed in with a fresh session id.
        $request->session()->regenerate(destroy: true);

        return redirect()->route('portal.account')->with('status', __('passwords.changed'));
    }

    public function destroySession(Request $request, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 404);
        abort_unless($this->sessions->revokeWebSession($request->user(), $session), 404);

        return back()->with('status', __('app.account.session_revoked'));
    }

    public function destroyToken(Request $request, int $token): RedirectResponse
    {
        abort_unless($this->sessions->revokeToken($request->user(), $token), 404);

        return back()->with('status', __('app.account.session_revoked'));
    }
}
