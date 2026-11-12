<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| Included inside the protected /api/v1/me group of routes/api.php (names are prefixed "api.v1.me.").
| Phase 8b — notification centre: the token owner's own notifications, preferences and push devices.
*/

Route::get('notifications', [V1\NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread-count', [V1\NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::post('notifications/read-all', [V1\NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [V1\NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
Route::get('notification-preferences', [V1\NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
Route::put('notification-preferences', [V1\NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
Route::get('devices', [V1\DeviceController::class, 'index'])->name('devices.index');
Route::post('devices', [V1\DeviceController::class, 'store'])->middleware('throttle:20,1')->name('devices.store');
Route::delete('devices', [V1\DeviceController::class, 'destroy'])->name('devices.destroy');
