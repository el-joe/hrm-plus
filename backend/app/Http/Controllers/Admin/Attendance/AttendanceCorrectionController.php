<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Actions\Attendance\ManageAttendanceCorrection;
use App\Enums\CorrectionSource;
use App\Enums\CorrectionStatus;
use App\Exceptions\ApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Attendance\CorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\ScheduleResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Corrections queue and individual attendance entry. Admin corrections are
 * applied at once unless the policy requires approval; employee requests
 * always wait for an approver (approval engine, workflow "attendance_correction").
 */
class AttendanceCorrectionController extends Controller
{
    public function index(Request $request, ApprovalService $approvals): View
    {
        Gate::authorize('viewAny', AttendanceCorrection::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CorrectionStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'mine' => ['nullable', 'boolean'],
        ]);
        $filters['status'] ??= $request->has('status') ? null : CorrectionStatus::Pending->value;

        $corrections = AttendanceCorrection::query()
            ->with(['employee', 'attendanceStatus', 'requester', 'latestApprovalRequest.currentStep', 'shift'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $query) => $query->search($search)))
            ->when($request->boolean('mine'), fn (Builder $query) => $query->whereIn('id', $approvals->pendingFor($request->user())
                ->where('approvable_type', (new AttendanceCorrection)->getMorphClass())->select('approvable_id')))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.attendance.corrections.index', ['corrections' => $corrections, 'filters' => $filters, 'statuses' => CorrectionStatus::cases()]);
    }

    public function create(Request $request, ScheduleResolver $schedules): View
    {
        $data = $request->validate(['employee_id' => ['nullable', 'integer'], 'date' => ['nullable', 'date_format:Y-m-d'], 'shift_id' => ['nullable', 'integer']]);
        $employee = isset($data['employee_id']) ? Employee::query()->findOrFail($data['employee_id']) : null;
        $date = $data['date'] ?? null;
        $resolved = $employee && $date ? $schedules->for($employee, $date) : null;

        $days = $employee && $date
            ? AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->with(['punches.rawEvent', 'shift'])->get()
            : collect();

        return view('admin.attendance.corrections.create', [
            'employee' => $employee,
            'date' => $date,
            'shiftId' => $data['shift_id'] ?? null,
            'resolved' => $resolved,
            'days' => $days,
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'statuses' => AttendanceStatus::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(CorrectionRequest $request, ManageAttendanceCorrection $corrections): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        try {
            $correction = $corrections->create($employee, $request->validated(), CorrectionSource::Admin, $request->user());
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        $message = $correction->status === CorrectionStatus::Applied ? 'attendance.corrections.applied' : 'attendance.corrections.submitted';
        $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', $correction->work_date)
            ->orderByRaw('shift_id = ? desc', [(int) $correction->shift_id])->first();

        return ($day && $correction->status === CorrectionStatus::Applied
            ? redirect()->route('admin.attendance.days.show', $day)
            : redirect()->route('admin.attendance.corrections.show', $correction))->with('status', __($message));
    }

    public function show(AttendanceCorrection $correction, ApprovalService $approvals): View
    {
        Gate::authorize('view', $correction);

        $correction->load(['employee', 'shift', 'attendanceStatus', 'requester', 'approvalRequests.actions.actor', 'rawEvents']);
        $timezone = app(ScheduleResolver::class)->for($correction->employee, $correction->work_date)->timezone;

        return view('admin.attendance.corrections.show', [
            'correction' => $correction,
            'timezone' => $timezone,
            'canDecide' => Gate::allows('decide', $correction),
            'day' => AttendanceDay::query()->where('employee_id', $correction->employee_id)->whereDate('work_date', $correction->work_date)->first(),
            'local' => fn ($instant) => AttendanceClock::localTime($instant, $timezone),
        ]);
    }

    public function decide(Request $request, AttendanceCorrection $correction, string $decision, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('decide', $correction);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $approvalRequest = $correction->latestApprovalRequest;

        try {
            $decision === 'approve'
                ? $approvals->approve($approvalRequest, $request->user(), $data['reason'] ?? null)
                : $approvals->reject($approvalRequest, $request->user(), $data['reason']);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return redirect()->route('admin.attendance.corrections.show', $correction)->with('status', __('attendance.corrections.'.($decision === 'approve' ? 'approved' : 'rejected')));
    }
}
