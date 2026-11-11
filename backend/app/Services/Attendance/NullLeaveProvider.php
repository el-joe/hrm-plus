<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Services\Attendance\Contracts\LeaveProvider;
use App\Services\Attendance\Data\LeaveDay;
use Carbon\CarbonImmutable;

/**
 * Default until the leave module exists: nobody is on approved leave.
 * (Leave recorded by hand is a manual "on leave" day status instead.)
 */
class NullLeaveProvider implements LeaveProvider
{
    public function leaveOn(Employee $employee, CarbonImmutable $date): ?LeaveDay
    {
        return null;
    }
}
