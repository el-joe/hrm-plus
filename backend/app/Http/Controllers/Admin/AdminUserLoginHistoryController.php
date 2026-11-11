<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The access history (successful and failed sign-ins) of one admin account.
 */
class AdminUserLoginHistoryController extends Controller
{
    public function __invoke(User $adminUser): View
    {
        Gate::authorize('view', $adminUser);

        return view('admin.admin-users.login-history', [
            'admin' => $adminUser,
            'logins' => $adminUser->loginHistories()->latest('id')->paginate(30),
        ]);
    }
}
