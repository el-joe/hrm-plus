<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

abstract class PasswordResetLinkController extends Controller
{
    abstract protected function area(): AuthArea;

    public function create(): View
    {
        return view($this->area()->viewDirectory().'.auth.forgot-password', ['area' => $this->area()]);
    }

    /**
     * The answer is the same whether or not the address belongs to an account
     * of this area, so the form cannot be used to discover accounts.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user !== null && $user->isActive() && $this->area()->admits($user)) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', __('passwords.sent'));
    }
}
