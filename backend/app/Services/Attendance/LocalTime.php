<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;

/**
 * Converts the "HH:MM" times typed on manual-entry screens (local to the
 * employee's branch) into UTC instants on an attendance date. A time before
 * the reference (e.g. a check-out at 06:00 after a 22:00 check-in) falls on
 * the next day.
 */
class LocalTime
{
    public static function toInstant(CarbonImmutable $date, string $time, string $timezone, ?CarbonImmutable $notBefore = null): CarbonImmutable
    {
        $instant = AttendanceClock::instant($date, $time, $timezone);

        while ($notBefore !== null && $instant->lte($notBefore)) {
            $instant = $instant->addDay();
        }

        return $instant;
    }
}
