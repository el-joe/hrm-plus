<?php

use App\Http\Controllers\Employee;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8a — training: always the signed-in user's own employee record.
*/

Route::get('training', [Employee\TrainingController::class, 'index'])->name('training');
Route::post('training/requests', [Employee\TrainingController::class, 'store'])->name('training.requests.store');
Route::get('training/enrolments/{enrolment}', [Employee\TrainingController::class, 'show'])->whereNumber('enrolment')->name('training.enrolments.show');
Route::post('training/enrolments/{enrolment}/cancel', [Employee\TrainingController::class, 'cancel'])->whereNumber('enrolment')->name('training.enrolments.cancel');
Route::post('training/certificates', [Employee\TrainingController::class, 'storeCertificate'])->name('training.certificates.store');
Route::get('training/certificates/{certificate}/download', [Employee\TrainingController::class, 'download'])->whereNumber('certificate')->name('training.certificates.download');
