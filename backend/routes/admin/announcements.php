<?php

use App\Http\Controllers\Admin\Announcements\AnnouncementController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8b — announcements. Record rules: AnnouncementPolicy (announcements.* + the admin's data scope
| for targeting) and App\Actions\Announcements\ManageAnnouncement.
*/

Route::resource('announcements', AnnouncementController::class);
Route::prefix('announcements/{announcement}')->name('announcements.')->group(function (): void {
    Route::post('publish', [AnnouncementController::class, 'publish'])->name('publish');
    Route::post('archive', [AnnouncementController::class, 'archive'])->name('archive');
    Route::post('pin', [AnnouncementController::class, 'pin'])->name('pin');
    Route::get('export', [AnnouncementController::class, 'export'])->name('export');
    Route::get('attachments/{attachment}', [AnnouncementController::class, 'attachment'])->whereNumber('attachment')->name('attachments.download');
    Route::delete('attachments/{attachment}', [AnnouncementController::class, 'destroyAttachment'])->whereNumber('attachment')->name('attachments.destroy');
});
