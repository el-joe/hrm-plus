<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Attendance\SaveWorkSchedule;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\ScheduleType;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Scheduling\WorkScheduleRequest;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Settings\SettingsService;
use App\Support\IndexSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Work schedules: weekly / rotating / monthly patterns of shifts and rest days.
 */
class WorkScheduleController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkSchedule::class);

        $filters = $this->filters($request);
        $query = $this->applyFilters(WorkSchedule::query()->with('days.shift')->withCount('assignments'), $filters);
        $sort = IndexSorting::apply($query, $request, ['code' => 'code', 'name' => WorkSchedule::localizedColumn()], default: 'code');

        return view('admin.scheduling.work-schedules.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(SettingsService $settings): View
    {
        Gate::authorize('create', WorkSchedule::class);

        return $this->form(new WorkSchedule(['type' => ScheduleType::Weekly, 'is_active' => true]), $settings);
    }

    public function store(WorkScheduleRequest $request, SaveWorkSchedule $save): RedirectResponse
    {
        $schedule = $save->handle(new WorkSchedule, $request->scheduleData(), $request->patternDays());

        return redirect()->route('admin.work-schedules.index')->with('status', __('structure.messages.created', ['name' => $schedule->name]));
    }

    public function edit(WorkSchedule $workSchedule, SettingsService $settings): View
    {
        Gate::authorize('update', $workSchedule);

        return $this->form($workSchedule->load('days'), $settings);
    }

    public function update(WorkScheduleRequest $request, WorkSchedule $workSchedule, SaveWorkSchedule $save): RedirectResponse
    {
        $save->handle($workSchedule, $request->scheduleData(), $request->patternDays());

        return redirect()->route('admin.work-schedules.index')->with('status', __('structure.messages.updated', ['name' => $workSchedule->name]));
    }

    public function destroy(WorkSchedule $workSchedule, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($workSchedule, $delete, 'admin.work-schedules.index');
    }

    public function status(Request $request, WorkSchedule $workSchedule, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $workSchedule, $changeStatus);
    }

    private function form(WorkSchedule $schedule, SettingsService $settings): View
    {
        $pattern = $schedule->exists ? $schedule->pattern()->all() : [];
        $usedShiftIds = collect($pattern)->flatten()->unique()->all();

        // Weekdays (0 = Sunday) in display order, starting from the configured first day of the week.
        $first = ['sunday' => 0, 'monday' => 1, 'saturday' => 6][$settings->get('localisation.first_day_of_week')] ?? 0;
        $weekdays = array_map(fn (int $offset): int => ($first + $offset) % 7, range(0, 6));

        return view('admin.scheduling.work-schedules.form', [
            'record' => $schedule,
            'pattern' => $pattern,
            'shifts' => Shift::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $usedShiftIds))
                ->orderBy('start_time')->orderBy('code')->get(),
            'types' => ScheduleType::cases(),
            'weekdays' => $weekdays,
            'maxCycle' => WorkScheduleRequest::MAX_CYCLE_DAYS,
        ]);
    }
}
