<?php

use App\Http\Controllers\Admin\Scheduling;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
|
| Attendance configuration (Phase 5a). Record-level rules live in the policies
| (ShiftPolicy, WorkSchedulePolicy, ScheduleAssignmentPolicy, HolidayPolicy,
| AttendancePolicyPolicy, AttendanceStatusPolicy, ScheduleChangeRequestPolicy)
| and the Form Requests.
*/

foreach (['shifts' => Scheduling\ShiftController::class, 'work-schedules' => Scheduling\WorkScheduleController::class, 'holidays' => Scheduling\HolidayController::class] as $resource => $controller) {
    $parameter = str_replace('-', '_', Str::singular($resource));

    Route::resource($resource, $controller)->except('show');
    Route::patch("{$resource}/{{$parameter}}/status", [$controller, 'status'])->name("{$resource}.status");
}

Route::resource('schedule-assignments', Scheduling\ScheduleAssignmentController::class)->except('show');

Route::get('schedule-calendar', [Scheduling\ScheduleCalendarController::class, 'index'])->name('schedule-calendar');
Route::get('schedule-calendar/day', [Scheduling\ScheduleCalendarController::class, 'edit'])->name('schedule-calendar.edit');
Route::post('schedule-calendar/override', [Scheduling\ScheduleCalendarController::class, 'override'])->name('schedule-calendar.override');
Route::delete('schedule-calendar/override', [Scheduling\ScheduleCalendarController::class, 'clear'])->name('schedule-calendar.override.clear');

Route::resource('schedule-changes', Scheduling\ScheduleChangeController::class)->only(['index', 'create', 'store']);
Route::post('schedule-changes/{schedule_change}/{decision}', [Scheduling\ScheduleChangeController::class, 'decide'])
    ->whereIn('decision', ['approve', 'reject'])->name('schedule-changes.decide');

Route::resource('attendance-policies', Scheduling\AttendancePolicyController::class);
Route::prefix('attendance-policies/{attendance_policy}')->name('attendance-policies.')->scopeBindings()->group(function (): void {
    Route::patch('status', [Scheduling\AttendancePolicyController::class, 'status'])->name('status');
    Route::get('versions/create', [Scheduling\AttendancePolicyController::class, 'createVersion'])->name('versions.create');
    Route::post('versions', [Scheduling\AttendancePolicyController::class, 'storeVersion'])->name('versions.store');
    Route::post('assignments', [Scheduling\AttendancePolicyController::class, 'storeAssignment'])->name('assignments.store');
    Route::delete('assignments/{assignment}', [Scheduling\AttendancePolicyController::class, 'destroyAssignment'])->name('assignments.destroy');
});

Route::resource('attendance-statuses', Scheduling\AttendanceStatusController::class)->except('show');
