<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Attendance\AttendanceTimesheet;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Monthly attendance calendar: one employee as a month grid (status, times,
 * shift per date), or a department / branch as an employee x date matrix
 * (25 employees per page). Statuses come from processed days; dates without
 * one show the expected schedule (rest day, holiday, scheduled).
 */
class AttendanceCalendarController extends Controller
{
    public function __invoke(Request $request, AttendanceTimesheet $timesheet, ScheduleResolver $schedules): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);
        [$from, $to] = AttendanceTimesheet::month($filters['month'] ?? null);
        $employee = isset($filters['employee_id']) ? Employee::query()->findOrFail($filters['employee_id']) : null;
        $dates = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $dates[] = $date;
        }

        $data = [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'dates' => $dates,
            'employee' => $employee,
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'departments' => Department::query()->orderBy('code')->get(),
            'branches' => Branch::query()->orderBy('code')->get(),
            'weeks' => [],
            'matrix' => null,
            'summary' => null,
        ];

        if ($employee !== null) {
            $rows = $timesheet->rows($employee, $from, $to);
            $data['summary'] = $timesheet->summary(collect($rows)->flatMap(fn (array $row): array => $row['days']));
            $firstDay = (int) array_search((string) setting('localisation.first_day_of_week'), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true);
            $cells = array_fill(0, ($from->dayOfWeek - $firstDay + 7) % 7, null);
            $data['weeks'] = array_chunk([...$cells, ...$rows], 7);
            $data['weekdays'] = array_map(fn (int $offset): string => CarbonImmutable::now()->startOfWeek($firstDay)->addDays($offset)->translatedFormat('D'), range(0, 6));

            return view('admin.attendance.calendar', $data);
        }

        if (isset($filters['department_id']) || isset($filters['branch_id'])) {
            $page = Employee::query()->employed()
                ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('department_id', $id))
                ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
                ->orderBy('employee_number')->paginate(25)->withQueryString();
            $days = AttendanceDay::query()->whereIn('employee_id', $page->getCollection()->modelKeys())
                ->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get()
                ->groupBy(fn (AttendanceDay $day): string => $day->employee_id.'|'.$day->work_date->format('Y-m-d'));
            $schedules->preloadOverrides($page->getCollection()->modelKeys(), $from, $to);

            $data['matrix'] = $page->through(fn (Employee $row): array => [
                'employee' => $row,
                'cells' => collect($schedules->forRange($row, $from, $to))->map(fn ($schedule, string $date): array => [
                    'schedule' => $schedule,
                    'days' => $days->get($row->id.'|'.$date, collect())->all(),
                ])->all(),
            ]);
        }

        return view('admin.attendance.calendar', $data);
    }
}
