<?php

namespace App\Http\Controllers\Employee\Auth;

use App\Enums\AuthArea;
use App\Http\Controllers\Auth\PasswordResetLinkController as BasePasswordResetLinkController;

class PasswordResetLinkController extends BasePasswordResetLinkController
{
    protected function area(): AuthArea
    {
        return AuthArea::Portal;
    }
}
