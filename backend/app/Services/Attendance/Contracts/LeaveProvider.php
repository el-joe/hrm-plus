<?php

namespace App\Services\Attendance\Contracts;

use App\Models\Employee;
use App\Services\Attendance\Data\LeaveDay;
use Carbon\CarbonImmutable;

/**
 * Hook through which approved leave reaches the attendance engine. Phase 6
 * (leave management) binds its own implementation in a service provider:
 *
 *     $this->app->bind(LeaveProvider::class, ApprovedLeaveProvider::class);
 *
 * and calls App\Services\Attendance\AttendanceRecalculation::dispatch() for
 * the affected dates whenever leave is approved or cancelled.
 */
interface LeaveProvider
{
    /**
     * Approved leave of the employee on the (local) date, or null.
     */
    public function leaveOn(Employee $employee, CarbonImmutable $date): ?LeaveDay;
}
