<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatusCategory;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

/**
 * The employee's own attendance for the portal and the mobile API: always
 * the signed-in employee's record, never another one.
 */
class PortalAttendance
{
    public function __construct(private readonly ScheduleResolver $schedules) {}

    /**
     * Expected shifts of the next days (portal "My schedule", API me/schedule).
     *
     * @return array<string, Data\ResolvedSchedule>
     */
    public function schedule(Employee $employee, string $from, string $to): array
    {
        return $this->schedules->forRange($employee, $from, $to);
    }

    /**
     * @return Collection<int, AttendanceCorrection>
     */
    public function corrections(Employee $employee, int $limit = 20): Collection
    {
        return AttendanceCorrection::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)->with(['attendanceStatus', 'shift'])
            ->latest('id')->limit($limit)->get();
    }

    public function ownCorrection(Employee $employee, int $id): ?AttendanceCorrection
    {
        return AttendanceCorrection::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->find($id);
    }

    /**
     * Statuses an employee may ask for (counted as present: official mission, remote work, ...).
     *
     * @return Collection<int, AttendanceStatus>
     */
    public function requestableStatuses(): Collection
    {
        return AttendanceStatus::query()->active()->where('category', AttendanceStatusCategory::Present)->orderBy('sort_order')->get();
    }
}
