<?php

use App\Http\Controllers\Api\Devices\DevicePushController;
use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — employee mobile application
|--------------------------------------------------------------------------
|
| Everything lives under /api/v1 and answers with the App\Support\ApiResponse
| envelope. Protected routes use BOTH "auth:sanctum" and "api.employee":
| admin accounts are refused. Add new domains as their own prefix group
| inside the protected group below.
|
*/

// Attendance devices push their logs here (per-device bearer token, not a user token). Phase 5b.
Route::post('v1/devices/{serial}/events', DevicePushController::class)
    ->where('serial', '[A-Za-z0-9._-]{1,100}')->middleware('throttle:device-push')->name('api.v1.devices.events');

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::post('auth/login', [V1\AuthController::class, 'login'])
        ->middleware('throttle:api-login')->name('auth.login');

    Route::middleware(['auth:sanctum', 'api.employee'])->group(function (): void {
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::get('me', [V1\AuthController::class, 'me'])->name('me');
            Route::post('refresh', [V1\AuthController::class, 'refresh'])->name('refresh');
            Route::post('logout', [V1\AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [V1\AuthController::class, 'logoutAll'])->name('logout-all');
            Route::get('login-history', [V1\LoginHistoryController::class, 'index'])->name('login-history');
        });

        Route::prefix('me')->name('me.')->group(function (): void {
            Route::get('profile', [V1\MeController::class, 'profile'])->name('profile');
            Route::get('documents', [V1\MeController::class, 'documents'])->name('documents.index');
            Route::get('documents/{document}', [V1\MeController::class, 'document'])->whereNumber('document')->name('documents.show');
            Route::get('documents/{document}/download', [V1\MeController::class, 'download'])->whereNumber('document')->name('documents.download');
            Route::get('onboarding', [V1\OnboardingController::class, 'show'])->name('onboarding.show');
            Route::post('onboarding/tasks/{task}/complete', [V1\OnboardingController::class, 'complete'])->whereNumber('task')->name('onboarding.tasks.complete');
            Route::get('schedule', [V1\AttendanceController::class, 'schedule'])->name('schedule');
            Route::get('attendance', [V1\AttendanceController::class, 'attendance'])->name('attendance.index');
            Route::get('attendance/corrections', [V1\AttendanceController::class, 'corrections'])->name('attendance.corrections.index');
            Route::post('attendance/corrections', [V1\AttendanceController::class, 'storeCorrection'])->name('attendance.corrections.store');

            // Mobile GPS / face attendance (Phase 5b).
            Route::get('attendance/today', [V1\MobileAttendanceController::class, 'today'])->name('attendance.today');
            Route::get('attendance/attempts', [V1\MobileAttendanceController::class, 'attempts'])->name('attendance.attempts');
            Route::post('attendance/check-in', [V1\MobileAttendanceController::class, 'checkIn'])->middleware('throttle:attendance-checkin')->name('attendance.check-in');
            Route::post('attendance/check-out', [V1\MobileAttendanceController::class, 'checkOut'])->middleware('throttle:attendance-checkin')->name('attendance.check-out');
            Route::get('face', [V1\FaceController::class, 'show'])->name('face.show');
            Route::post('face/consent', [V1\FaceController::class, 'consent'])->name('face.consent');
            Route::delete('face/consent', [V1\FaceController::class, 'withdraw'])->name('face.withdraw');
            // Leave (Phase 6).
            Route::get('leave/types', [V1\LeaveController::class, 'types'])->name('leave.types');
            Route::get('leave/balances', [V1\LeaveController::class, 'balances'])->name('leave.balances');
            Route::get('leave/requests', [V1\LeaveController::class, 'index'])->name('leave.requests.index');
            Route::post('leave/requests/preview', [V1\LeaveController::class, 'preview'])->name('leave.requests.preview');
            Route::post('leave/requests', [V1\LeaveController::class, 'store'])->name('leave.requests.store');
            Route::get('leave/requests/{leaveRequest}', [V1\LeaveController::class, 'show'])->whereNumber('leaveRequest')->name('leave.requests.show');
            Route::post('leave/requests/{leaveRequest}/cancel', [V1\LeaveController::class, 'cancel'])->whereNumber('leaveRequest')->name('leave.requests.cancel');
            Route::post('leave/requests/{leaveRequest}/attachments', [V1\LeaveController::class, 'addAttachments'])->whereNumber('leaveRequest')->name('leave.requests.attachments.store');
            Route::get('leave/requests/{leaveRequest}/attachments/{attachment}', [V1\LeaveController::class, 'attachment'])
                ->whereNumber(['leaveRequest', 'attachment'])->name('leave.requests.attachments.show');
            // Loans and advances (Phase 7a).
            Route::get('loans', [V1\LoanController::class, 'index'])->name('loans.index');
            Route::get('loans/limits', [V1\LoanController::class, 'limits'])->name('loans.limits');
            Route::post('loans', [V1\LoanController::class, 'store'])->name('loans.store');
            Route::get('loans/{loan}', [V1\LoanController::class, 'show'])->whereNumber('loan')->name('loans.show');
            Route::post('loans/{loan}/cancel', [V1\LoanController::class, 'cancel'])->whereNumber('loan')->name('loans.cancel');
            // Payslips (Phase 7b): own issued payslips only, every view / download logged.
            Route::get('payslips', [V1\PayslipController::class, 'index'])->name('payslips.index');
            Route::get('payslips/summary', [V1\PayslipController::class, 'summary'])->name('payslips.summary');
            Route::get('payslips/{payslip}', [V1\PayslipController::class, 'show'])->whereNumber('payslip')->name('payslips.show');
            Route::get('payslips/{payslip}/pdf', [V1\PayslipController::class, 'pdf'])->whereNumber('payslip')->name('payslips.pdf');
            Route::post('face/enrol', [V1\FaceController::class, 'enrol'])->middleware('throttle:attendance-checkin')->name('face.enrol');
        });

        Route::get('company', [V1\CompanyController::class, 'show'])->name('company.show');
        Route::get('branches', [V1\BranchController::class, 'index'])->name('branches.index');
        Route::get('branches/{branch}', [V1\BranchController::class, 'show'])->whereNumber('branch')->name('branches.show');
    });
});
