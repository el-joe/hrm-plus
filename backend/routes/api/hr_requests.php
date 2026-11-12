<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| Included inside the protected /api/v1/me group of routes/api.php (names are prefixed "api.v1.me.").
| Phase 8b — HR requests: always the token owner's own requests; internal notes are never returned.
*/

Route::get('requests', [V1\HrRequestController::class, 'index'])->name('requests.index');
Route::get('requests/categories', [V1\HrRequestController::class, 'categories'])->name('requests.categories');
Route::get('requests/profile-fields', [V1\HrRequestController::class, 'profileFields'])->name('requests.profile-fields');
Route::post('requests', [V1\HrRequestController::class, 'store'])->name('requests.store');
Route::get('requests/{hrRequest}', [V1\HrRequestController::class, 'show'])->whereNumber('hrRequest')->name('requests.show');
Route::post('requests/{hrRequest}/replies', [V1\HrRequestController::class, 'reply'])->whereNumber('hrRequest')->name('requests.replies.store');
Route::post('requests/{hrRequest}/attachments', [V1\HrRequestController::class, 'attach'])->whereNumber('hrRequest')->name('requests.attachments.store');
Route::get('requests/{hrRequest}/attachments/{attachment}', [V1\HrRequestController::class, 'attachment'])
    ->whereNumber(['hrRequest', 'attachment'])->name('requests.attachments.show');
Route::post('requests/{hrRequest}/cancel', [V1\HrRequestController::class, 'cancel'])->whereNumber('hrRequest')->name('requests.cancel');
