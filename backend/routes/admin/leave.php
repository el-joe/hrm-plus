<?php

use App\Http\Controllers\Admin\Leave;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
|
| Leave management (Phase 6). Configuration (types, policies) is authorised by
| LeaveTypePolicy / LeavePolicyPolicy; requests by LeaveRequestPolicy (data scope
| through the employee, decisions through the approval engine); balances by
| leave_balances.* plus the employee's data scope.
*/

Route::resource('leave-types', Leave\LeaveTypeController::class)->except('show');
Route::patch('leave-types/{leave_type}/status', [Leave\LeaveTypeController::class, 'status'])->name('leave-types.status');

Route::resource('leave-policies', Leave\LeavePolicyController::class)->except('show');
Route::patch('leave-policies/{leave_policy}/status', [Leave\LeavePolicyController::class, 'status'])->name('leave-policies.status');

Route::prefix('leave-balances')->name('leave-balances.')->group(function (): void {
    Route::get('/', [Leave\LeaveBalanceController::class, 'index'])->can('leave_balances.view')->name('index');
    Route::get('export', [Leave\LeaveBalanceController::class, 'export'])->can('leave_balances.export')->middleware('throttle:exports')->name('export');
    Route::get('{employee}', [Leave\LeaveBalanceController::class, 'show'])->whereNumber('employee')->can('leave_balances.view')->name('show');
    Route::post('{employee}/transactions', [Leave\LeaveBalanceController::class, 'store'])->whereNumber('employee')->can('leave_balances.update')->name('transactions.store');
});

Route::get('leave-calendar', Leave\LeaveCalendarController::class)->can('leaves.view')->name('leave-calendar');

Route::get('leave-reports', [Leave\LeaveReportController::class, 'index'])->name('leave-reports.index');
Route::get('leave-reports/{report}', [Leave\LeaveReportController::class, 'show'])->where('report', '[a-z_]+')->name('leave-reports.show');
Route::get('leave-reports/{report}/export', [Leave\LeaveReportController::class, 'export'])->where('report', '[a-z_]+')->middleware('throttle:exports')->name('leave-reports.export');

Route::resource('leave-requests', Leave\LeaveRequestController::class)->only(['index', 'create', 'store', 'show']);
Route::prefix('leave-requests/{leave_request}')->name('leave-requests.')->group(function (): void {
    Route::post('decide/{decision}', [Leave\LeaveRequestController::class, 'decide'])->whereIn('decision', ['approve', 'reject'])->name('decide');
    Route::post('cancel', [Leave\LeaveRequestController::class, 'cancel'])->name('cancel');
    Route::get('attachments/{attachment}', [Leave\LeaveRequestController::class, 'attachment'])->whereNumber('attachment')->name('attachments.download');
});
