<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use App\Services\Attendance\HolidayCalendar;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data of the leave calendar (employees × days of a month) and of the
 * "who else is away" hints. Pending and taken leave are shown; data scopes
 * apply through the Employee / LeaveRequest queries of the admin area.
 */
class LeaveCalendar
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly HolidayCalendar $holidays,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{rows: list<array{employee: Employee, cells: array<string, array{leave: list<LeaveRequestDay>, rest: bool, holiday: ?string, employed: bool}>}>, away: array<string, int>, conflicts: array<string, list<string>>, holidays: array<string, string>}
     */
    public function month(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ids = $employees->modelKeys();
        $days = LeaveRequestDay::query()
            ->whereIn('employee_id', $ids)->where('is_counted', true)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->whereHas('request', fn (Builder $query) => $query->withoutGlobalScopes()->inStatus(LeaveRequestStatus::active()))
            ->with(['request' => fn ($query) => $query->withoutGlobalScopes()->with('leaveType')])
            ->get()
            ->groupBy(fn (LeaveRequestDay $day): string => $day->employee_id.'|'.$day->date->format('Y-m-d'));

        $this->schedules->preloadOverrides($ids, $from, $to);
        $rows = [];

        foreach ($employees as $employee) {
            $cells = [];

            foreach ($this->schedules->forRange($employee, $from, $to) as $date => $resolved) {
                $cells[$date] = [
                    'leave' => $days->get($employee->id.'|'.$date, collect())->all(),
                    'rest' => $resolved->isRestDay,
                    'holiday' => $resolved->holiday?->name,
                    'employed' => $resolved->isEmployed(),
                ];
            }

            $rows[] = ['employee' => $employee, 'cells' => $cells];
        }

        return [
            'rows' => $rows,
            'away' => $this->awayCounts($from, $to),
            'conflicts' => $this->departmentConflicts($from, $to),
            'holidays' => array_map(fn ($holiday): string => $holiday->name, $this->holidays->between($from, $to, null)),
        ];
    }

    /**
     * Employees (inside the admin's data scope) away per date: date => count.
     *
     * @return array<string, int>
     */
    public function awayCounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->awayQuery($from, $to)
            ->selectRaw('leave_request_days.date as day, COUNT(DISTINCT leave_request_days.employee_id) as total')
            ->groupBy('leave_request_days.date')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day): array => [substr((string) $day, 0, 10) => (int) $total])
            ->all();
    }

    /**
     * Dates where a department has at least setting leave.calendar_conflict_threshold employees away:
     * date => department names.
     *
     * @return array<string, list<string>>
     */
    public function departmentConflicts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $threshold = max(2, (int) $this->settings->get('leave.calendar_conflict_threshold'));
        $rows = $this->awayQuery($from, $to)
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->selectRaw('leave_request_days.date as day, departments.id as department_id, departments.name_ar, departments.name_en, COUNT(DISTINCT leave_request_days.employee_id) as total')
            ->groupBy('leave_request_days.date', 'departments.id', 'departments.name_ar', 'departments.name_en')
            ->havingRaw('COUNT(DISTINCT leave_request_days.employee_id) >= ?', [$threshold])
            ->get();
        $conflicts = [];
        $column = app()->getLocale() === 'en' ? 'name_en' : 'name_ar';

        foreach ($rows as $row) {
            $conflicts[substr((string) $row->day, 0, 10)][] = (string) ($row->{$column} ?: $row->name_en).' ('.$row->total.')';
        }

        return $conflicts;
    }

    /**
     * Other employees of the same department away (pending or taken) during the request.
     *
     * @return Collection<int, LeaveRequest>
     */
    public function conflictsFor(LeaveRequest $request): Collection
    {
        $departmentId = $request->employee?->department_id;

        if ($departmentId === null) {
            return collect();
        }

        return LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->whereKeyNot($request->id)->where('employee_id', '!=', $request->employee_id)
            ->inStatus(LeaveRequestStatus::active())
            ->overlapping($request->start_date->format('Y-m-d'), $request->end_date->format('Y-m-d'))
            ->whereHas('employee', fn (Builder $query) => $query->where('department_id', $departmentId))
            ->orderBy('start_date')->limit(20)->get();
    }

    /**
     * Counted days of active requests of employees the admin can see.
     *
     * @return Builder<Employee>
     */
    private function awayQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Employee::query()
            ->join('leave_request_days', 'leave_request_days.employee_id', '=', 'employees.id')
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_days.leave_request_id')
            ->where('leave_request_days.is_counted', true)
            ->whereBetween('leave_request_days.date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->whereIn('leave_requests.status', array_map(fn (LeaveRequestStatus $status): string => $status->value, LeaveRequestStatus::active()));
    }
}
