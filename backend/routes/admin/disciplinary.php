<?php

use App\Http\Controllers\Admin\Disciplinary;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8a — disciplinary. Restricted: DisciplinaryCasePolicy (disciplinary.view for the case file,
| reporters see their own reports' status), DisciplinaryActionPolicy (approval engine) and
| ViolationTypePolicy (disciplinary.manage). Every case view is access-logged.
*/

Route::resource('violation-types', Disciplinary\ViolationTypeController::class)->except('show');
Route::patch('violation-types/{violation_type}/status', [Disciplinary\ViolationTypeController::class, 'status'])->name('violation-types.status');

Route::get('disciplinary-cases/export', [Disciplinary\DisciplinaryCaseController::class, 'export'])->middleware('throttle:exports')->name('disciplinary-cases.export');
Route::resource('disciplinary-cases', Disciplinary\DisciplinaryCaseController::class)->only(['index', 'create', 'store', 'show']);
Route::prefix('disciplinary-cases/{disciplinary_case}')->name('disciplinary-cases.')->group(function (): void {
    Route::post('investigate', [Disciplinary\DisciplinaryCaseController::class, 'investigate'])->name('investigate');
    Route::post('notes', [Disciplinary\DisciplinaryCaseController::class, 'storeNote'])->name('notes.store');
    Route::post('evidence', [Disciplinary\DisciplinaryCaseController::class, 'storeEvidence'])->name('evidence.store');
    Route::get('evidence/{evidence}', [Disciplinary\DisciplinaryCaseController::class, 'evidence'])->whereNumber('evidence')->name('evidence.download');
    Route::put('findings', [Disciplinary\DisciplinaryCaseController::class, 'findings'])->name('findings');
    Route::post('dismiss', [Disciplinary\DisciplinaryCaseController::class, 'dismiss'])->name('dismiss');
    Route::post('close', [Disciplinary\DisciplinaryCaseController::class, 'close'])->name('close');
    Route::get('actions/create', [Disciplinary\DisciplinaryActionController::class, 'create'])->name('actions.create');
    Route::post('actions', [Disciplinary\DisciplinaryActionController::class, 'store'])->name('actions.store');
    Route::post('actions/{action}/decide/{decision}', [Disciplinary\DisciplinaryActionController::class, 'decide'])
        ->whereNumber('action')->whereIn('decision', ['approve', 'reject'])->name('actions.decide');
    Route::post('actions/{action}/cancel', [Disciplinary\DisciplinaryActionController::class, 'cancel'])->whereNumber('action')->name('actions.cancel');
});
