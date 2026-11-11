<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Attendance\BulkAttendance;
use App\Services\Leave\LeaveCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Leave calendar: employees × days of a month by company, branch or
 * department (data scope applies), or "my team" (the admin's direct
 * reports). Shows pending and approved leave by type, rest days, holidays,
 * the number of people away per day and department conflicts.
 */
class LeaveCalendarController extends Controller
{
    public const PER_PAGE = 25;

    public function __invoke(Request $request, LeaveCalendar $calendar): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'leave_type_id' => ['nullable', 'integer'],
            'team' => ['nullable', 'in:mine'],
            'search' => ['nullable', 'string', 'max:100'],
            'only_away' => ['nullable', 'boolean'],
        ]);
        $today = BulkAttendance::today();
        $from = isset($filters['month']) ? CarbonImmutable::createFromFormat('!Y-m', $filters['month'], 'UTC') : $today->startOfMonth();
        $to = $from->endOfMonth()->startOfDay();
        $managerId = $request->user()->employee_id;

        $employees = Employee::query()->employed()
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when(($filters['team'] ?? null) === 'mine', fn (Builder $query) => $query->where('manager_id', $managerId ?? 0))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($request->boolean('only_away') || ($filters['leave_type_id'] ?? null), fn (Builder $query) => $query->whereHas('leaveRequests', fn (Builder $leave) => $leave
                ->overlapping($from->format('Y-m-d'), $to->format('Y-m-d'))->inStatus(LeaveRequestStatus::active())
                ->when($filters['leave_type_id'] ?? null, fn (Builder $leave, int $type) => $leave->where('leave_type_id', $type))))
            ->orderBy('employee_number')
            ->paginate(self::PER_PAGE)->withQueryString();

        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $days[] = $date;
        }

        return view('admin.leave.calendar', [
            'filters' => $filters,
            'from' => $from,
            'days' => $days,
            'today' => $today,
            'employees' => $employees,
            'grid' => $calendar->month($employees->getCollection(), $from, $to),
            'types' => LeaveType::query()->active()->ordered()->get(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'departments' => Department::query()->active()->orderBy('code')->get(),
            'hasTeam' => $managerId !== null,
        ]);
    }
}
