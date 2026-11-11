<?php

namespace App\Services\Attendance;

use App\Jobs\ProcessAttendance;
use App\Models\Employee;

/**
 * Triggers the engine for an employee's dates: now (in this request) or on
 * the queue (ProcessAttendance job). Phase 6 calls this when leave is
 * approved; schedule changes and corrections call it for the dates they touch.
 */
class AttendanceRecalculation
{
    /**
     * @param  list<string>  $dates  Y-m-d
     */
    public function queue(int $employeeId, array $dates): void
    {
        $dates = $this->normalise($dates);

        if ($dates !== []) {
            ProcessAttendance::dispatch($employeeId, $dates);
        }
    }

    /**
     * @param  list<string>  $dates  Y-m-d
     */
    public function now(int $employeeId, array $dates): void
    {
        $dates = $this->normalise($dates);
        $employee = Employee::query()->withoutGlobalScopes()->find($employeeId);

        if ($employee !== null && $dates !== []) {
            app(AttendanceEngine::class)->processDates($employee, $dates);
        }
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function normalise(array $dates): array
    {
        $dates = array_values(array_unique(array_map(fn (string $date): string => substr($date, 0, 10), $dates)));
        sort($dates);

        return $dates;
    }
}
