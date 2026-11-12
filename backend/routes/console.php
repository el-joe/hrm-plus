<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Recurring HR operations (run `php artisan schedule:work` locally, or the
| usual cron entry in production). Times are in the application timezone.
*/
Schedule::command('employees:apply-scheduled-changes')->dailyAt('00:10')->withoutOverlapping()->onOneServer();
Schedule::command('employees:send-expiry-reminders')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('recruitment:send-interview-reminders')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('recruitment:expire-offers')->dailyAt('00:20')->withoutOverlapping()->onOneServer();
Schedule::command('offboarding:detect-contract-expiries')->dailyAt('06:30')->withoutOverlapping()->onOneServer();
// Attendance (Phase 5a): yesterday's days once the day is over (absences appear even without any
// punch), and a sweep for raw events whose processing job never ran.
Schedule::command('attendance:process')->dailyAt('01:30')->withoutOverlapping()->onOneServer();
Schedule::command('attendance:process --pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();
// Attendance capture (Phase 5b): pull devices that are due (honours the backoff after failures), and the
// biometric retention purge.
Schedule::command('attendance:sync-devices')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('face:purge')->dailyAt('02:15')->withoutOverlapping()->onOneServer();
// Leave (Phase 6): open the new leave year (rolls each balance once; a no-op on other days), then
// credit entitlements due today and expire carried-over days past their expiry date.
Schedule::command('leave:rollover')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
Schedule::command('leave:accrue')->dailyAt('00:40')->withoutOverlapping()->onOneServer();
// Payroll (Phase 7b): attendance / leave changes after a period was finalised become pending adjustments.
Schedule::command('payroll:scan-corrections')->dailyAt('02:45')->withoutOverlapping()->onOneServer();
// Phase 8a: training certificates expiring within the reminder window (employee + training managers, once
// per certificate), and approved performance reviews not acknowledged in time (reminder to the employee).
Schedule::command('training:send-certificate-reminders')->dailyAt('07:10')->withoutOverlapping()->onOneServer();
Schedule::command('performance:send-acknowledgement-reminders')->dailyAt('07:20')->withoutOverlapping()->onOneServer();
// Phase 8b: HR request SLA escalation, scheduled announcements, notification automations (attendance alerts,
// people events, probation, training reminders, approvals digest) and retention purge.
Schedule::command('hr-requests:escalate')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('announcements:publish-scheduled')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('notifications:attendance-alerts')->hourlyAt(15)->withoutOverlapping()->onOneServer();
Schedule::command('notifications:people-events')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
Schedule::command('notifications:training-reminders')->dailyAt('07:40')->withoutOverlapping()->onOneServer();
Schedule::command('notifications:approvals-digest')->weekdays()->at('08:00')->withoutOverlapping()->onOneServer();
Schedule::command('system:purge-retention')->dailyAt('03:15')->withoutOverlapping()->onOneServer();
// Phase 8c: queued report export files are kept 7 days.
Schedule::command('reports:prune-exports')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
