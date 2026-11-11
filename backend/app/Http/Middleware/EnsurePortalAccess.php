<?php

namespace App\Http\Middleware;

use App\Enums\AuthArea;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate of the employee portal (alias "portal"). Passes an active account
 * signed in on the employee guard, or an active admin signed in on the admin
 * guard whose account is linked to an employee profile (they see their own
 * record only). $request->user() is the portal user afterwards.
 *
 * Portal controllers must always load data through the signed-in user's own
 * employee_id — never from an id in the URL without an ownership check.
 */
class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ([AuthArea::Portal->guard(), AuthArea::Admin->guard()] as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user instanceof User && $user->isActive() && $user->canAccessPortal()) {
                Auth::shouldUse($guard);

                return $next($request);
            }
        }

        if (Auth::guard(AuthArea::Portal->guard())->check()) {
            Auth::guard(AuthArea::Portal->guard())->logout();

            return redirect()->route(AuthArea::Portal->loginRoute())->withErrors(['email' => __('auth.inactive')]);
        }

        return redirect()->guest(route(AuthArea::Portal->loginRoute()));
    }
}
