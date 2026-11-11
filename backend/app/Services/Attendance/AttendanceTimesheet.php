<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Services\Attendance\Data\ResolvedSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * An employee's month (or any range): the expected schedule of every date
 * next to the processed days, and the totals. Shared by the admin
 * timesheet, the portal "My attendance" page and the mobile API.
 */
class AttendanceTimesheet
{
    public function __construct(private readonly ScheduleResolver $schedules) {}

    /**
     * @return list<array{date: CarbonImmutable, schedule: ResolvedSchedule, days: list<AttendanceDay>}>
     */
    public function rows(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $this->days($employee, $from, $to)->groupBy(fn (AttendanceDay $day): string => $day->work_date->format('Y-m-d'));
        $rows = [];

        foreach ($this->schedules->forRange($employee, $from, $to) as $date => $schedule) {
            $rows[] = ['date' => AttendanceClock::date($date), 'schedule' => $schedule, 'days' => $days->get($date, collect())->values()->all()];
        }

        return $rows;
    }

    /**
     * @return Collection<int, AttendanceDay>
     */
    public function days(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return AttendanceDay::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->with(['shift', 'attendanceStatus', 'holiday'])
            ->orderBy('work_date')->orderBy('scheduled_start_at')
            ->get();
    }

    /**
     * Totals of a set of days (minutes, and day counts by status).
     *
     * @param  iterable<AttendanceDay>  $days
     * @return array<string, int>
     */
    public function summary(iterable $days): array
    {
        $totals = [
            'present_days' => 0, 'late_days' => 0, 'absent_days' => 0, 'incomplete_days' => 0, 'leave_days' => 0,
            'holidays' => 0, 'rest_days' => 0, 'manual_days' => 0, 'missing_punches' => 0,
            'scheduled_minutes' => 0, 'worked_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0,
            'shortfall_minutes' => 0, 'overtime_minutes' => 0, 'overtime_approved_minutes' => 0, 'overtime_pending_minutes' => 0,
        ];

        foreach ($days as $day) {
            $key = match ($day->status) {
                AttendanceDayStatus::Present => 'present_days',
                AttendanceDayStatus::Late => 'late_days',
                AttendanceDayStatus::Absent => 'absent_days',
                AttendanceDayStatus::Incomplete => 'incomplete_days',
                AttendanceDayStatus::OnLeave => 'leave_days',
                AttendanceDayStatus::Holiday => 'holidays',
                AttendanceDayStatus::RestDay => 'rest_days',
            };
            $totals[$key]++;
            $totals['manual_days'] += $day->is_manual ? 1 : 0;
            $totals['missing_punches'] += $day->hasFlag('missing_check_in') || $day->hasFlag('missing_check_out') ? 1 : 0;

            foreach (['scheduled_minutes', 'worked_minutes', 'late_minutes', 'early_leave_minutes', 'shortfall_minutes', 'overtime_minutes'] as $field) {
                $totals[$field] += (int) $day->{$field};
            }

            $totals['overtime_approved_minutes'] += $day->overtime_status === OvertimeStatus::Approved ? (int) $day->overtime_approved_minutes : 0;
            $totals['overtime_pending_minutes'] += $day->overtime_status === OvertimeStatus::Pending ? (int) $day->overtime_minutes : 0;
        }

        return $totals;
    }

    /**
     * First and last day of a "Y-m" month (default: the current month in the company timezone).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function month(?string $month): array
    {
        $start = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1
            ? AttendanceClock::date($month.'-01')
            : BulkAttendance::today()->startOfMonth();

        return [$start, $start->endOfMonth()->startOfDay()];
    }
}
