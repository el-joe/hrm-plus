<?php

use App\Http\Controllers\Employee\AnnouncementController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8b — announcements: only live announcements addressed to the signed-in employee.
*/

Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement')->name('announcements.show');
Route::post('announcements/{announcement}/acknowledge', [AnnouncementController::class, 'acknowledge'])->whereNumber('announcement')->name('announcements.acknowledge');
Route::get('announcements/{announcement}/attachments/{attachment}', [AnnouncementController::class, 'attachment'])
    ->whereNumber(['announcement', 'attachment'])->name('announcements.attachments.download');
