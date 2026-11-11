<?php

namespace App\Http\Middleware;

use App\Enums\AuthArea;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate of the admin area (alias "admin"). Only an active Admin account signed
 * in on the admin guard passes; employees can never get through. It also
 * switches on the admin's data scopes for the rest of the request.
 *
 * This only proves "is an admin". Every route still needs its own permission
 * check: ->can('module.action') or a policy.
 */
class EnsureAdminAccess
{
    public function __construct(private readonly DataScopeManager $dataScopes) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard(AuthArea::Admin->guard());
        $user = $guard->user();

        if (! $user instanceof User) {
            return redirect()->guest(route(AuthArea::Admin->loginRoute()));
        }

        if (! $user->isAdmin() || ! $user->isActive()) {
            $guard->logout();

            return redirect()->route(AuthArea::Admin->loginRoute())->withErrors(['email' => __('auth.inactive')]);
        }

        Auth::shouldUse(AuthArea::Admin->guard());
        $this->dataScopes->activate($user);

        return $next($request);
    }
}
