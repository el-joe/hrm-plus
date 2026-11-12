<?php

use App\Http\Controllers\Employee\PerformanceController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8a — performance: always the signed-in user's own employee record.
*/

Route::get('performance', [PerformanceController::class, 'index'])->name('performance');
Route::post('performance/goals', [PerformanceController::class, 'storeGoal'])->name('performance.goals.store');
Route::post('performance/goals/{goal}/progress', [PerformanceController::class, 'progress'])->whereNumber('goal')->name('performance.goals.progress');
Route::get('performance/reviews/{review}', [PerformanceController::class, 'showReview'])->whereNumber('review')->name('performance.reviews.show');
Route::post('performance/reviews/{review}/self-assessment', [PerformanceController::class, 'selfAssessment'])->whereNumber('review')->name('performance.reviews.self');
Route::post('performance/reviews/{review}/acknowledge', [PerformanceController::class, 'acknowledge'])->whereNumber('review')->name('performance.reviews.acknowledge');
