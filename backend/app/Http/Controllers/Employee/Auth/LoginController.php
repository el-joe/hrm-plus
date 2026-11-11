<?php

namespace App\Http\Controllers\Employee\Auth;

use App\Enums\AuthArea;
use App\Http\Controllers\Auth\LoginController as BaseLoginController;

class LoginController extends BaseLoginController
{
    protected function area(): AuthArea
    {
        return AuthArea::Portal;
    }
}
