<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Http\Controllers\Concerns\NotificationCentreActions;
use App\Http\Controllers\Controller;

/**
 * The signed-in user's notification centre in the admin area: inbox, preferences, own devices.
 */
class NotificationController extends Controller
{
    use NotificationCentreActions;

    protected function area(): string
    {
        return 'admin';
    }
}
