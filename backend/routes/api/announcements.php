<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the protected /api/v1/me group of routes/api.php (names are prefixed "api.v1.me.").
| Phase 8b — announcements: only live announcements addressed to the token owner's employee record.
*/

Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement')->name('announcements.show');
Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'read'])->whereNumber('announcement')->name('announcements.read');
Route::post('announcements/{announcement}/acknowledge', [AnnouncementController::class, 'acknowledge'])->whereNumber('announcement')->name('announcements.acknowledge');
Route::get('announcements/{announcement}/attachments/{attachment}', [AnnouncementController::class, 'attachment'])
    ->whereNumber(['announcement', 'attachment'])->name('announcements.attachments.show');
