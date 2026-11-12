<?php

use App\Http\Controllers\Admin\Reports\ReportController;
use App\Http\Controllers\Admin\Reports\ReportExportController;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8c — reports centre. Access: reports.view (+ the report's own requirements, checked by
| ReportFilterRequest); exports: reports.export. My exports: the signed-in admin's own files only.
*/

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/exports', [ReportExportController::class, 'index'])->name('reports.exports.index');
Route::get('reports/exports/{export}/download', [ReportExportController::class, 'download'])->whereNumber('export')->name('reports.exports.download');
Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z_]+')->name('reports.show');
Route::get('reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z_]+')->middleware('throttle:exports')->name('reports.export');
