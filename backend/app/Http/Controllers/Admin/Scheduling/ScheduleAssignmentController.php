<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Enums\AttendanceScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scheduling\ScheduleAssignmentRequest;
use App\Models\ScheduleAssignment;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who works which schedule from when. The resolver applies them with the
 * precedence employee > group > department > branch > company; days already
 * processed are recalculated by the nightly attendance:process run or a
 * manual reprocess.
 */
class ScheduleAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ScheduleAssignment::class);

        $filters = $request->validate([
            'scope_type' => ['nullable', Rule::in(array_map(fn ($scope) => $scope->value, AttendanceScope::forSchedules()))],
            'work_schedule_id' => ['nullable', 'integer'],
        ]);

        $records = ScheduleAssignment::query()->with('schedule')
            ->when($filters['scope_type'] ?? null, fn ($query, $type) => $query->where('scope_type', $type))
            ->when($filters['work_schedule_id'] ?? null, fn ($query, $id) => $query->where('work_schedule_id', $id))
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.scheduling.assignments.index', [
            'records' => $records,
            'filters' => $filters,
            'schedules' => WorkSchedule::query()->orderBy('code')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ScheduleAssignment::class);

        $type = AttendanceScope::tryFrom((string) $request->query('scope_type'));
        $type = $type !== null && in_array($type, AttendanceScope::forSchedules(), true) ? $type : AttendanceScope::Employee;

        return $this->form(new ScheduleAssignment([
            'scope_type' => $type,
            'scope_id' => $request->integer('scope_id') ?: null,
            'effective_from' => today(),
        ]));
    }

    public function store(ScheduleAssignmentRequest $request): RedirectResponse
    {
        ScheduleAssignment::create([...$request->assignmentData(), 'created_by' => $request->user()->id]);

        return redirect()->route('admin.schedule-assignments.index')->with('status', __('schedules.assignments.saved'));
    }

    public function edit(ScheduleAssignment $scheduleAssignment): View
    {
        Gate::authorize('update', $scheduleAssignment);

        return $this->form($scheduleAssignment);
    }

    public function update(ScheduleAssignmentRequest $request, ScheduleAssignment $scheduleAssignment): RedirectResponse
    {
        $scheduleAssignment->update($request->assignmentData());

        return redirect()->route('admin.schedule-assignments.index')->with('status', __('schedules.assignments.saved'));
    }

    public function destroy(ScheduleAssignment $scheduleAssignment): RedirectResponse
    {
        Gate::authorize('delete', $scheduleAssignment);

        $scheduleAssignment->delete();

        return redirect()->route('admin.schedule-assignments.index')->with('status', __('schedules.assignments.deleted'));
    }

    private function form(ScheduleAssignment $assignment): View
    {
        $types = AttendanceScope::forSchedules();

        return view('admin.scheduling.assignments.form', [
            'record' => $assignment,
            'types' => $types,
            'scopeOptions' => ScopeOptions::for($types, $assignment->scope_type, $assignment->scope_id),
            'schedules' => WorkSchedule::query()->activeOrKeep($assignment->work_schedule_id)->orderBy('code')->get(),
        ]);
    }
}
