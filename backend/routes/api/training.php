<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| Included inside the protected /api/v1/me group of routes/api.php (names are prefixed "api.v1.me.").
| Phase 8a — training: always the token owner's own employee record.
*/

Route::get('training', [V1\TrainingController::class, 'summary'])->name('training.summary');
Route::get('training/enrolments', [V1\TrainingController::class, 'enrolments'])->name('training.enrolments.index');
Route::get('training/enrolments/{enrolment}', [V1\TrainingController::class, 'enrolment'])->whereNumber('enrolment')->name('training.enrolments.show');
Route::post('training/enrolments/{enrolment}/cancel', [V1\TrainingController::class, 'cancel'])->whereNumber('enrolment')->name('training.enrolments.cancel');
Route::get('training/sessions', [V1\TrainingController::class, 'sessions'])->name('training.sessions.index');
Route::post('training/requests', [V1\TrainingController::class, 'store'])->name('training.requests.store');
Route::get('training/certificates', [V1\TrainingController::class, 'certificates'])->name('training.certificates.index');
Route::post('training/certificates', [V1\TrainingController::class, 'storeCertificate'])->name('training.certificates.store');
Route::get('training/certificates/{certificate}/download', [V1\TrainingController::class, 'download'])->whereNumber('certificate')->name('training.certificates.download');
