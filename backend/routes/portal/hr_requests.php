<?php

use App\Http\Controllers\Employee;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8b — My requests: always the signed-in user's own requests (incl. letters and profile change
| requests); internal notes are never shown.
*/

Route::get('requests', [Employee\HrRequestController::class, 'index'])->name('requests.index');
Route::get('requests/new', [Employee\HrRequestController::class, 'create'])->name('requests.create');
Route::get('requests/profile-change', [Employee\HrRequestController::class, 'profileChange'])->name('requests.profile-change');
Route::post('requests', [Employee\HrRequestController::class, 'store'])->name('requests.store');
Route::get('requests/{hrRequest}', [Employee\HrRequestController::class, 'show'])->whereNumber('hrRequest')->name('requests.show');
Route::post('requests/{hrRequest}/replies', [Employee\HrRequestController::class, 'reply'])->whereNumber('hrRequest')->name('requests.reply');
Route::get('requests/{hrRequest}/attachments/{attachment}', [Employee\HrRequestController::class, 'attachment'])
    ->whereNumber(['hrRequest', 'attachment'])->name('requests.attachments.download');
Route::post('requests/{hrRequest}/cancel', [Employee\HrRequestController::class, 'cancel'])->whereNumber('hrRequest')->name('requests.cancel');
