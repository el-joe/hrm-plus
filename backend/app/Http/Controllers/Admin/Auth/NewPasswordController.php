<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\AuthArea;
use App\Http\Controllers\Auth\NewPasswordController as BaseNewPasswordController;

class NewPasswordController extends BaseNewPasswordController
{
    protected function area(): AuthArea
    {
        return AuthArea::Admin;
    }
}
