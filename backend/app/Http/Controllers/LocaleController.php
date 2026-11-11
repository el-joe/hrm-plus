<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Switches the interface language. The choice is kept in the session and,
 * for signed-in users, saved on every account signed in on this browser.
 */
class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        $request->session()->put('locale', $locale);

        foreach (['admin', 'employee'] as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user instanceof User && $user->locale !== $locale) {
                $user->update(['locale' => $locale]);
            }
        }

        return back();
    }
}
