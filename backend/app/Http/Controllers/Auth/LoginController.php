<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Enums\AuthArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Session login shared by the admin area and the employee portal. Each area
 * has its own thin subclass that only names the area.
 */
abstract class LoginController extends Controller
{
    abstract protected function area(): AuthArea;

    public function create(): View
    {
        return view($this->area()->viewDirectory().'.auth.login', ['area' => $this->area()]);
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticate): RedirectResponse
    {
        $area = $this->area();

        $user = $authenticate->handle($area, $request->validated('email'), $request->validated('password'), $request);

        Auth::guard($area->guard())->login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route($area->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $area = $this->area();

        Auth::guard($area->guard())->logout();

        // The other area may still be signed in on this browser (an admin who
        // also uses the portal), so only drop the whole session when it is not.
        $otherGuard = $area === AuthArea::Admin ? AuthArea::Portal->guard() : AuthArea::Admin->guard();

        if (Auth::guard($otherGuard)->check()) {
            $request->session()->regenerateToken();
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route($area->loginRoute());
    }
}
