<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Models\AttendanceDay;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\Reports\AttendanceReports;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base of the reports-centre attendance reports read from processed days (attendance_days, written
 * only by the AttendanceEngine). The day's branch / department snapshot drives those filters.
 */
abstract class AttendanceDaysReport extends Report
{
    public function area(): string
    {
        return 'attendance';
    }

    public function maxRangeDays(): int
    {
        return AttendanceReports::MAX_RANGE_DAYS;
    }

    public function sortable(): array
    {
        return ['date' => 'attendance_days.work_date', 'employee_number' => 'employees.employee_number'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDay>
     */
    protected function days(array $filters): Builder
    {
        $query = AttendanceDay::query()
            ->with(['employee.branch', 'employee.department', 'shift', 'attendanceStatus'])
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->select('attendance_days.*')
            ->whereBetween('attendance_days.work_date', [$filters['from'], $filters['to']])
            ->orderBy('attendance_days.work_date')->orderBy('employees.employee_number');

        return ReportFilters::applyEmployeeFilters($query, 'attendance_days.employee_id', $filters, [
            'branch' => 'attendance_days.branch_id', 'department' => 'attendance_days.department_id',
        ]);
    }

    /**
     * @return array<string, Column>
     */
    protected function dayColumns(): array
    {
        return [
            ...$this->employeeColumns(),
            'date' => Column::date($this->label('date')),
            'shift' => Column::code($this->label('shift')),
            'scheduled' => Column::code($this->label('scheduled')),
            'check_in' => Column::code($this->label('check_in')),
            'check_out' => Column::code($this->label('check_out')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dayCells(AttendanceDay $day): array
    {
        $tz = $day->timezone;

        return [
            ...$this->employeeCells($day->employee),
            'date' => $day->work_date,
            'shift' => $day->shift?->code,
            'scheduled' => trim(AttendanceClock::localTime($day->scheduled_start_at, $tz).' – '.AttendanceClock::localTime($day->scheduled_end_at, $tz), ' –'),
            'check_in' => AttendanceClock::localTime($day->first_in_at, $tz),
            'check_out' => AttendanceClock::localTime($day->last_out_at, $tz),
        ];
    }

    protected function minutes(?int $minutes): string
    {
        return Duration::format((int) $minutes, '0:00');
    }
}
