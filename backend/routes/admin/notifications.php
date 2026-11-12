<?php

use App\Http\Controllers\Admin\Notifications\NotificationController;
use App\Http\Controllers\Admin\Notifications\NotificationDeliveryController;
use App\Http\Controllers\Admin\Notifications\NotificationTemplateController;
use App\Http\Controllers\Admin\Settings\ChannelSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8b — notification centre. The own inbox / preferences need no permission (always the signed-in
| admin's rows); templates need notifications.view (read) / notifications.manage (edit, reset); the
| delivery log notifications.view; the mail / push tools on the settings screens settings.manage.
*/

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
Route::get('notification-preferences', [NotificationController::class, 'editPreferences'])->name('notification-preferences.edit');
Route::put('notification-preferences', [NotificationController::class, 'updatePreferences'])->name('notification-preferences.update');

Route::get('notification-templates', [NotificationTemplateController::class, 'index'])->can('notifications.view')->name('notification-templates.index');
Route::get('notification-templates/{event}', [NotificationTemplateController::class, 'edit'])->where('event', '[a-z0-9_]+')->can('notifications.view')->name('notification-templates.edit');
Route::put('notification-templates/{event}', [NotificationTemplateController::class, 'update'])->where('event', '[a-z0-9_]+')->can('notifications.manage')->name('notification-templates.update');
Route::post('notification-templates/{event}/reset', [NotificationTemplateController::class, 'reset'])->where('event', '[a-z0-9_]+')->can('notifications.manage')->name('notification-templates.reset');

Route::get('notification-deliveries', [NotificationDeliveryController::class, 'index'])->can('notifications.view')->name('notification-deliveries.index');

Route::middleware('can:settings.manage')->group(function (): void {
    Route::post('settings/mail/test', [ChannelSettingsController::class, 'mailTest'])->middleware('throttle:10,1')->name('settings.mail.test');
    Route::post('settings/push/credentials', [ChannelSettingsController::class, 'uploadCredentials'])->name('settings.push.credentials');
    Route::delete('settings/push/credentials', [ChannelSettingsController::class, 'removeCredentials'])->name('settings.push.credentials.destroy');
    Route::post('settings/push/test', [ChannelSettingsController::class, 'pushTest'])->middleware('throttle:10,1')->name('settings.push.test');
});
