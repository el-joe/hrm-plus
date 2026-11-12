<?php

use App\Http\Controllers\Employee\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8b — notification centre: always the signed-in user's own notifications, preferences and devices.
*/

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
Route::get('notification-preferences', [NotificationController::class, 'editPreferences'])->name('notification-preferences.edit');
Route::put('notification-preferences', [NotificationController::class, 'updatePreferences'])->name('notification-preferences.update');
Route::delete('notification-preferences/devices/{device}', [NotificationController::class, 'destroyDevice'])->whereNumber('device')->name('notification-preferences.devices.destroy');
