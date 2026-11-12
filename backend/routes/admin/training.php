<?php

use App\Http\Controllers\Admin\Training;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8a — training. Every action is authorised by its policy (TrainingCataloguePolicy subclasses,
| TrainingSessionPolicy, TrainingEnrolmentPolicy, TrainingCertificatePolicy, EmployeeSkillPolicy,
| TrainingBudgetPolicy) inside the controllers.
*/

foreach ([
    'training-providers' => Training\TrainingProviderController::class,
    'training-programmes' => Training\TrainingProgrammeController::class,
    'training-courses' => Training\TrainingCourseController::class,
    'skills' => Training\SkillController::class,
] as $resource => $controller) {
    Route::resource($resource, $controller)->except('show');
    Route::patch($resource.'/{'.str_replace('-', '_', Str::singular($resource)).'}/status', [$controller, 'status'])->name($resource.'.status');
}

Route::resource('training-sessions', Training\TrainingSessionController::class)->except('destroy');
Route::prefix('training-sessions/{training_session}')->name('training-sessions.')->controller(Training\TrainingSessionController::class)->group(function (): void {
    Route::post('cancel', 'cancel')->name('cancel');
    Route::post('complete', 'complete')->name('complete');
    Route::post('nominate', 'nominate')->name('nominate');
    Route::post('attendance', 'attendance')->name('attendance');
    Route::post('outcomes', 'outcomes')->name('outcomes');
});

Route::prefix('training-enrolments')->name('training-enrolments.')->controller(Training\TrainingEnrolmentController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('export', 'export')->name('export');
    Route::get('{training_enrolment}', 'show')->whereNumber('training_enrolment')->name('show');
    Route::post('{training_enrolment}/decide/{decision}', 'decide')->whereNumber('training_enrolment')->whereIn('decision', ['approve', 'reject'])->name('decide');
    Route::post('{training_enrolment}/cancel', 'cancel')->whereNumber('training_enrolment')->name('cancel');
    Route::post('{training_enrolment}/outcome', 'outcome')->whereNumber('training_enrolment')->name('outcome');
});

Route::get('training-certificates/export', [Training\TrainingCertificateController::class, 'export'])->name('training-certificates.export');
Route::get('training-certificates/{training_certificate}/download', [Training\TrainingCertificateController::class, 'download'])->name('training-certificates.download');
Route::resource('training-certificates', Training\TrainingCertificateController::class)->except('show');

Route::get('skills-matrix', [Training\SkillsMatrixController::class, 'index'])->name('skills-matrix');
Route::post('skills-matrix', [Training\SkillsMatrixController::class, 'update'])->name('skills-matrix.update');

Route::resource('training-budgets', Training\TrainingBudgetController::class)->only(['index', 'store', 'update', 'destroy']);
