<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Attendance\ManageScheduleChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scheduling\ScheduleOverrideRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Shift;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Month / week grid of the expected shifts of each employee (ScheduleResolver),
 * with holidays and date overrides; cells open a form to change one date.
 */
class ScheduleCalendarController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request, ScheduleResolver $resolver, SettingsService $settings): View
    {
        abort_unless($request->user()->can('schedules.view'), 403);

        $filters = $request->validate([
            'view' => ['nullable', 'in:month,week'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $view = $filters['view'] ?? 'month';
        $anchor = AttendanceClock::date($filters['date'] ?? now(app(AttendanceClock::class)->companyTimezone()));
        [$from, $to, $previous, $next] = $this->range($view, $anchor, $settings);

        $employees = Employee::query()->employed()
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['group_id'] ?? null, fn ($query, $id) => $query->whereHas('groups', fn ($query) => $query->whereKey($id)))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->search($search))
            ->orderBy('employee_number')
            ->paginate(self::PER_PAGE)->withQueryString();

        $resolver->preloadOverrides($employees->getCollection()->modelKeys(), $from, $to);
        $rows = $employees->getCollection()->map(fn (Employee $employee): array => [
            'employee' => $employee,
            'days' => $resolver->forRange($employee, $from, $to),
        ]);

        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $days[] = $date;
        }

        return view('admin.scheduling.calendar.index', [
            'filters' => $filters,
            'view' => $view,
            'anchor' => $anchor,
            'days' => $days,
            'previous' => $previous,
            'next' => $next,
            'today' => AttendanceClock::date(now(app(AttendanceClock::class)->companyTimezone())),
            'employees' => $employees,
            'rows' => $rows,
            'shifts' => Shift::query()->active()->orderBy('start_time')->get(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'departments' => Department::query()->active()->orderBy('code')->get(),
            'groups' => EmployeeGroup::query()->active()->orderBy('code')->get(),
            'canEdit' => $request->user()->can('schedules.update'),
        ]);
    }

    /**
     * The no-JavaScript form for one employee and date.
     */
    public function edit(Request $request, ScheduleResolver $resolver): View
    {
        abort_unless($request->user()->can('schedules.update'), 403);

        $data = $request->validate(['employee_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'view' => ['nullable', 'in:month,week']]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        Gate::authorize('view', $employee);

        return view('admin.scheduling.calendar.edit', [
            'employee' => $employee,
            'date' => $data['date'],
            'view' => $data['view'] ?? 'month',
            'resolved' => $resolver->for($employee, $data['date']),
            'shifts' => Shift::query()->active()->orderBy('start_time')->get(),
        ]);
    }

    public function override(ScheduleOverrideRequest $request, ManageScheduleChange $changes): RedirectResponse
    {
        $employee = $request->employee();
        $date = $request->input('date');
        $changes->setOverride($employee, $date, array_map('intval', (array) $request->input('shift_ids', [])), $request->user(), note: $request->input('note'));

        return redirect()->route('admin.schedule-calendar', ['date' => $date, 'view' => $request->input('view', 'month')])
            ->with('status', __('schedules.calendar.override_saved', ['employee' => $employee->name, 'date' => $date]));
    }

    public function clear(ScheduleOverrideRequest $request, ManageScheduleChange $changes): RedirectResponse
    {
        $employee = $request->employee();
        $date = $request->input('date');
        $changes->clearOverride($employee, $date, $request->user());

        return redirect()->route('admin.schedule-calendar', ['date' => $date, 'view' => $request->input('view', 'month')])
            ->with('status', __('schedules.calendar.override_cleared', ['employee' => $employee->name, 'date' => $date]));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function range(string $view, CarbonImmutable $anchor, SettingsService $settings): array
    {
        if ($view === 'week') {
            $first = ['sunday' => 0, 'monday' => 1, 'saturday' => 6][$settings->get('localisation.first_day_of_week')] ?? 0;
            $from = $anchor->subDays(($anchor->dayOfWeek - $first + 7) % 7);

            return [$from, $from->addDays(6), $from->subWeek(), $from->addWeek()];
        }

        $from = $anchor->startOfMonth();

        return [$from, $from->endOfMonth()->startOfDay(), $from->subMonthNoOverflow(), $from->addMonthNoOverflow()];
    }
}
