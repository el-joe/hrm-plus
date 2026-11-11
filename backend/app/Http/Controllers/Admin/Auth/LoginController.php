<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\AuthArea;
use App\Http\Controllers\Auth\LoginController as BaseLoginController;

class LoginController extends BaseLoginController
{
    protected function area(): AuthArea
    {
        return AuthArea::Admin;
    }
}
