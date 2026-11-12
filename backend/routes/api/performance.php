<?php

use App\Http\Controllers\Api\V1\PerformanceController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the protected /api/v1/me group of routes/api.php (names are prefixed "api.v1.me.").
| Phase 8a — performance: always the token owner's own employee record.
*/

Route::get('performance', [PerformanceController::class, 'summary'])->name('performance.summary');
Route::get('performance/goals', [PerformanceController::class, 'goals'])->name('performance.goals.index');
Route::post('performance/goals/{goal}/progress', [PerformanceController::class, 'progress'])->whereNumber('goal')->name('performance.goals.progress');
Route::get('performance/reviews', [PerformanceController::class, 'reviews'])->name('performance.reviews.index');
Route::get('performance/reviews/{review}', [PerformanceController::class, 'review'])->whereNumber('review')->name('performance.reviews.show');
Route::post('performance/reviews/{review}/self-assessment', [PerformanceController::class, 'selfAssessment'])->whereNumber('review')->name('performance.reviews.self');
Route::post('performance/reviews/{review}/acknowledge', [PerformanceController::class, 'acknowledge'])->whereNumber('review')->name('performance.reviews.acknowledge');
