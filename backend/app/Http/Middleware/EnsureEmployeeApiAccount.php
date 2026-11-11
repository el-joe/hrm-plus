<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:sanctum (alias "api.employee"). The mobile API serves
 * employee accounts only; a token that belongs to an admin or to a
 * deactivated account is refused on every request, not just at login.
 */
class EnsureEmployeeApiAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isEmployee()) {
            return ApiResponse::error(__('auth.employee_only'), 403, 'forbidden');
        }

        if (! $user->isActive()) {
            return ApiResponse::error(__('auth.inactive'), 403, 'account_inactive');
        }

        return $next($request);
    }
}
