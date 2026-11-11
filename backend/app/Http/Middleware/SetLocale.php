<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the request locale.
 *
 * - Web: the signed-in user's saved preference, then the session.
 * - API: the Accept-Language header the app sends, then the token owner's
 *   saved preference.
 * - Otherwise the system setting localisation.default_locale (ar by default).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.supported_locales'));

        $locale = $request->is('api/*')
            ? (filled($request->header('Accept-Language')) ? $request->getPreferredLanguage($supported) : null) ?? $this->userLocale('sanctum')
            : $this->userLocale('admin', 'employee') ?? ($request->hasSession() ? $request->session()->get('locale') : null);

        $locale ??= setting('localisation.default_locale');

        if (is_string($locale) && array_key_exists($locale, config('app.supported_locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function userLocale(string ...$guards): ?string
    {
        foreach ($guards as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user instanceof User && $user->locale !== null) {
                return $user->locale;
            }
        }

        return null;
    }
}
