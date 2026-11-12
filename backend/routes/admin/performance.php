<?php

use App\Http\Controllers\Admin\Performance;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8a — performance. Reviews and goals are authorised by PerformanceReviewPolicy /
| PerformanceGoalPolicy (data scope + reporting line); configuration (cycles, scales,
| competencies) by performance.manage.
*/

Route::prefix('performance-reviews')->name('performance-reviews.')->controller(Performance\PerformanceReviewController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('export', 'export')->name('export');
    Route::get('{performance_review}', 'show')->whereNumber('performance_review')->name('show');
    Route::post('{performance_review}/assessment', 'assess')->whereNumber('performance_review')->name('assess');
    Route::post('{performance_review}/decide/{decision}', 'decide')->whereNumber('performance_review')->whereIn('decision', ['approve', 'reject'])->name('decide');
    Route::post('{performance_review}/skip-self', 'skipSelf')->whereNumber('performance_review')->name('skip-self');
    Route::post('{performance_review}/cancel', 'cancel')->whereNumber('performance_review')->name('cancel');
});

Route::resource('performance-goals', Performance\PerformanceGoalController::class)->except(['show', 'destroy']);
Route::post('performance-goals/{performance_goal}/progress', [Performance\PerformanceGoalController::class, 'progress'])->name('performance-goals.progress');
Route::patch('performance-goals/{performance_goal}/status', [Performance\PerformanceGoalController::class, 'status'])->name('performance-goals.status');

Route::resource('performance-cycles', Performance\PerformanceCycleController::class);
Route::post('performance-cycles/{performance_cycle}/launch', [Performance\PerformanceCycleController::class, 'launch'])->name('performance-cycles.launch');
Route::post('performance-cycles/{performance_cycle}/close', [Performance\PerformanceCycleController::class, 'close'])->name('performance-cycles.close');

Route::resource('competencies', Performance\CompetencyController::class)->except('show');
Route::patch('competencies/{competency}/status', [Performance\CompetencyController::class, 'status'])->name('competencies.status');

Route::resource('rating-scales', Performance\RatingScaleController::class)->except('show');
Route::patch('rating-scales/{rating_scale}/status', [Performance\RatingScaleController::class, 'status'])->name('rating-scales.status');
