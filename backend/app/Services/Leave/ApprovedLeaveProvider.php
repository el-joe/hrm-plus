<?php

namespace App\Services\Leave;

use App\Enums\LeaveDayPart;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequestDay;
use App\Services\Attendance\Contracts\LeaveProvider;
use App\Services\Attendance\Data\LeaveDay;
use Carbon\CarbonImmutable;

/**
 * Approved leave for the attendance engine (bound to LeaveProvider in
 * AppServiceProvider). A date counts when an approved request (or one whose
 * cancellation is still pending) charges it: rest days and holidays the
 * leave type does not count stay rest days / holidays. Partial days (half
 * days, hours) are reported as half-day leave unless they add up to a full day.
 */
class ApprovedLeaveProvider implements LeaveProvider
{
    public function leaveOn(Employee $employee, CarbonImmutable $date): ?LeaveDay
    {
        $days = LeaveRequestDay::query()
            ->where('employee_id', $employee->id)->whereDate('date', $date->format('Y-m-d'))->where('is_counted', true)
            ->whereHas('request', fn ($query) => $query->withoutGlobalScopes()
                ->whereIn('status', array_map(fn (LeaveRequestStatus $status): string => $status->value, LeaveRequestStatus::taken())))
            ->with(['request' => fn ($query) => $query->withoutGlobalScopes()->with('leaveType')])
            ->orderByRaw("day_part = 'full' desc")->orderBy('id')
            ->get();

        if ($days->isEmpty()) {
            return null;
        }

        $first = $days->first();
        $type = $first->request->leaveType;
        $total = $days->reduce(fn (string $carry, LeaveRequestDay $day): string => bcadd($carry, (string) $day->amount, 2), '0.00');
        $full = $first->day_part === LeaveDayPart::Full || bccomp($total, '1.00', 2) >= 0;

        return new LeaveDay(
            reference: 'leave_request:'.$first->leave_request_id,
            typeCode: $type->code,
            isPaid: $type->is_paid,
            halfDay: ! $full,
            attendanceStatusId: $full ? $type->attendance_status_id : null,
        );
    }
}
