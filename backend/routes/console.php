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
