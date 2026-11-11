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
