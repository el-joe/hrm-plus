<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\Attendance\AttendanceEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalculate attendance days of one employee. Safe to run any number of
 * times (the engine is idempotent).
 */
class ProcessAttendance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $dates  Y-m-d
     */
    public function __construct(public int $employeeId, public array $dates) {}

    public function handle(AttendanceEngine $engine): void
    {
        $employee = Employee::query()->withoutGlobalScopes()->find($this->employeeId);

        if ($employee !== null) {
            $engine->processDates($employee, $this->dates);
        }
    }
}
