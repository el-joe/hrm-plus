<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Actions\Attendance\DecideOvertime;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceRawEvent;
use App\Services\Attendance\AttendanceEngine;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One employee's processed day: raw events (every source, voided ones too)
 * -> normalised punches -> the calculation breakdown, plus adjustments after
 * a lock and the corrections of that day. Actions: reprocess, decide
 * overtime, void a raw event.
 */
class AttendanceDayController extends Controller
{
    public function show(AttendanceDay $day): View
    {
        Gate::authorize('view', $day);

        $day->load(['employee.branch', 'employee.department', 'shift.breaks', 'attendanceStatus', 'holiday', 'policyVersion.policy', 'lock', 'punches.rawEvent', 'adjustments.creator']);
        $local = CarbonImmutable::parse($day->work_date->format('Y-m-d'), $day->timezone);

        $events = AttendanceRawEvent::query()
            ->with(['attendanceStatus', 'recorder', 'shift', 'origin'])
            ->where('employee_id', $day->employee_id)
            ->where(fn ($query) => $query
                ->whereIn('id', $day->punches->pluck('attendance_raw_event_id'))
                ->orWhereDate('work_date', $day->work_date->format('Y-m-d'))
                ->orWhere(fn ($query) => $query->whereNotNull('voided_at')->whereNull('work_date')
                    ->whereBetween('occurred_at', [$local->subHours(12)->utc(), $local->addHours(36)->utc()])))
            ->orderBy('occurred_at')->orderBy('id')
            ->get();

        return view('admin.attendance.day', [
            'day' => $day,
            'events' => $events,
            'siblings' => AttendanceDay::query()->where('employee_id', $day->employee_id)->whereDate('work_date', $day->work_date)->whereKeyNot($day->id)->with('shift')->get(),
            'corrections' => AttendanceCorrection::query()->with(['requester', 'attendanceStatus'])
                ->where('employee_id', $day->employee_id)->whereDate('work_date', $day->work_date)->latest('id')->get(),
        ]);
    }

    public function reprocess(AttendanceDay $day, AttendanceEngine $engine, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('reprocess', $day);

        $employee = $day->employee()->withoutGlobalScopes()->firstOrFail();
        $date = $day->work_date->format('Y-m-d');
        $days = $engine->process($employee, $date);
        $audit->log('attendance.reprocessed', $employee, meta: ['date' => $date]);

        $target = collect($days)->firstWhere('shift_key', $day->shift_key) ?? ($days[0] ?? null);

        return $target
            ? redirect()->route('admin.attendance.days.show', $target)->with('status', __('attendance.days.reprocessed'))
            : redirect()->route('admin.attendance.daily', ['date' => $date])->with('status', __('attendance.days.reprocessed'));
    }

    public function overtime(Request $request, AttendanceDay $day, DecideOvertime $decide): RedirectResponse
    {
        Gate::authorize('decideOvertime', $day);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:'.$day->overtime_minutes],
        ]);

        $decide->handle($day, $data['decision'] === 'approve', isset($data['minutes']) ? (int) $data['minutes'] : null, $request->user());

        return back()->with('status', __('attendance.days.overtime_'.($data['decision'] === 'approve' ? 'approved' : 'rejected')));
    }

    public function voidEvent(Request $request, AttendanceRawEvent $event, RawEventRecorder $recorder, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.update') && $event->employee_id !== null, 403);
        abort_if($event->employee()->doesntExist(), 404);

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $recorder->void($event, $request->user(), $reason);
        $audit->log('attendance.event_voided', $event, meta: ['reason' => $reason, 'source' => $event->source->value]);

        return back()->with('status', __('attendance.days.event_voided'));
    }
}
