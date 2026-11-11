<?php

use App\Http\Controllers\Admin\Attendance;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
|
| Attendance operations (Phase 5a). Every route carries a permission; records
| are additionally checked against the admin's data scope in the policies
| (AttendanceDayPolicy, AttendanceCorrectionPolicy, AttendanceManualBatchPolicy).
*/

Route::prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/', [Attendance\DailyAttendanceController::class, 'index'])->can('attendance.view')->name('daily');
    Route::get('export', [Attendance\DailyAttendanceController::class, 'export'])->can('attendance.export')->name('daily.export');
    Route::get('timesheet', [Attendance\TimesheetController::class, 'index'])->can('attendance.view')->name('timesheet');
    Route::get('timesheet/export', [Attendance\TimesheetController::class, 'export'])->can('attendance.export')->name('timesheet.export');

    Route::get('days/{day}', [Attendance\AttendanceDayController::class, 'show'])->whereNumber('day')->name('days.show');
    Route::post('days/{day}/reprocess', [Attendance\AttendanceDayController::class, 'reprocess'])->whereNumber('day')->name('days.reprocess');
    Route::post('days/{day}/overtime', [Attendance\AttendanceDayController::class, 'overtime'])->whereNumber('day')->name('days.overtime');
    Route::post('events/{event}/void', [Attendance\AttendanceDayController::class, 'voidEvent'])->whereNumber('event')->name('events.void');

    Route::get('corrections', [Attendance\AttendanceCorrectionController::class, 'index'])->name('corrections.index');
    Route::get('corrections/create', [Attendance\AttendanceCorrectionController::class, 'create'])->can('attendance.update')->name('corrections.create');
    Route::post('corrections', [Attendance\AttendanceCorrectionController::class, 'store'])->can('attendance.update')->name('corrections.store');
    Route::get('corrections/{correction}', [Attendance\AttendanceCorrectionController::class, 'show'])->whereNumber('correction')->name('corrections.show');
    Route::post('corrections/{correction}/{decision}', [Attendance\AttendanceCorrectionController::class, 'decide'])
        ->whereNumber('correction')->whereIn('decision', ['approve', 'reject'])->name('corrections.decide');

    Route::get('bulk', [Attendance\BulkAttendanceController::class, 'create'])->can('attendance.create')->name('bulk.create');
    Route::post('bulk', [Attendance\BulkAttendanceController::class, 'store'])->can('attendance.create')->name('bulk.store');

    Route::get('batches', [Attendance\AttendanceBatchController::class, 'index'])->can('attendance.view')->name('batches.index');
    Route::get('batches/{batch}', [Attendance\AttendanceBatchController::class, 'show'])->whereNumber('batch')->name('batches.show');
    Route::post('batches/{batch}/confirm', [Attendance\AttendanceBatchController::class, 'confirm'])->whereNumber('batch')->name('batches.confirm');
    Route::post('batches/{batch}/discard', [Attendance\AttendanceBatchController::class, 'discard'])->whereNumber('batch')->name('batches.discard');
    Route::post('batches/{batch}/{decision}', [Attendance\AttendanceBatchController::class, 'decide'])
        ->whereNumber('batch')->whereIn('decision', ['approve', 'reject'])->name('batches.decide');

    Route::get('import', [Attendance\AttendanceImportController::class, 'create'])->can('attendance.create')->name('import');
    Route::get('import/template', [Attendance\AttendanceImportController::class, 'template'])->can('attendance.create')->name('import.template');
    Route::post('import', [Attendance\AttendanceImportController::class, 'analyse'])->can('attendance.create')->name('import.analyse');
    Route::post('import/{token}', [Attendance\AttendanceImportController::class, 'store'])->can('attendance.create')->name('import.store');

    Route::get('locks', [Attendance\AttendanceLockController::class, 'index'])->can('attendance.manage')->name('locks.index');
    Route::post('locks', [Attendance\AttendanceLockController::class, 'store'])->can('attendance.manage')->name('locks.store');
    Route::post('locks/{lock}/unlock', [Attendance\AttendanceLockController::class, 'unlock'])->whereNumber('lock')->can('attendance.manage')->name('locks.unlock');
    Route::post('reprocess', [Attendance\AttendanceLockController::class, 'reprocess'])->can('attendance.manage')->name('reprocess');
});
