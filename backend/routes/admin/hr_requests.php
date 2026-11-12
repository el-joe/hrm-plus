<?php

use App\Http\Controllers\Admin\HrRequests;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8b — HR requests and services. Record rules: HrRequestPolicy (+ HrRequestVisibility),
| HrRequestCategoryPolicy and HrLetterTemplatePolicy (hr_requests.manage).
*/

Route::resource('hr-request-categories', HrRequests\HrRequestCategoryController::class)->except('show');
Route::resource('hr-letter-templates', HrRequests\HrLetterTemplateController::class);

Route::get('hr-requests/export', [HrRequests\HrRequestController::class, 'export'])->name('hr-requests.export');
Route::resource('hr-requests', HrRequests\HrRequestController::class)->only(['index', 'create', 'store', 'show']);
Route::prefix('hr-requests/{hr_request}')->name('hr-requests.')->group(function (): void {
    Route::post('assign', [HrRequests\HrRequestController::class, 'assign'])->name('assign');
    Route::post('start', [HrRequests\HrRequestController::class, 'start'])->name('start');
    Route::post('priority', [HrRequests\HrRequestController::class, 'priority'])->name('priority');
    Route::post('messages', [HrRequests\HrRequestController::class, 'reply'])->name('messages.store');
    Route::post('attachments', [HrRequests\HrRequestController::class, 'storeAttachments'])->name('attachments.store');
    Route::get('attachments/{attachment}', [HrRequests\HrRequestController::class, 'attachment'])->whereNumber('attachment')->name('attachments.download');
    Route::post('resolve', [HrRequests\HrRequestController::class, 'resolve'])->name('resolve');
    Route::post('reject', [HrRequests\HrRequestController::class, 'reject'])->name('reject');
    Route::post('cancel', [HrRequests\HrRequestController::class, 'cancel'])->name('cancel');
    Route::post('decide/{decision}', [HrRequests\HrRequestController::class, 'decide'])->whereIn('decision', ['approve', 'reject'])->name('decide');
    Route::post('letter', [HrRequests\HrRequestController::class, 'issueLetter'])->name('letter');
    Route::post('changes/apply', [HrRequests\HrRequestController::class, 'applyChanges'])->name('changes.apply');
    Route::post('changes/reject', [HrRequests\HrRequestController::class, 'rejectChanges'])->name('changes.reject');
});
