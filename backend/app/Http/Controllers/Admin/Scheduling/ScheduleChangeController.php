<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Attendance\ManageScheduleChange;
use App\Enums\RequestStatus;
use App\Enums\ScheduleChangeType;
use App\Exceptions\ApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scheduling\ScheduleChangeRequestForm;
use App\Models\Employee;
use App\Models\ScheduleChangeRequest;
use App\Models\Shift;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\ScheduleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Shift changes and swaps, decided through the approval engine (workflow
 * "schedule_change"); approval writes schedule overrides.
 */
class ScheduleChangeController extends Controller
{
    public function index(Request $request, ScheduleResolver $resolver): View
    {
        Gate::authorize('viewAny', ScheduleChangeRequest::class);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(RequestStatus::class)]]);

        $records = ScheduleChangeRequest::query()
            ->with(['employee', 'swapEmployee', 'shift', 'requester', 'latestApprovalRequest.currentStep'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("status = 'pending' desc")->latest('id')
            ->paginate(20)->withQueryString();

        $current = [];

        foreach ($records as $record) {
            foreach (array_filter([$record->employee, $record->swapEmployee]) as $employee) {
                $current[$record->id][$employee->id] = $resolver->for($employee, $record->work_date)->shifts;
            }
        }

        return view('admin.scheduling.changes.index', ['records' => $records, 'filters' => $filters, 'current' => $current]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ScheduleChangeRequest::class);

        return view('admin.scheduling.changes.form', [
            'employees' => Employee::query()->active()->orderBy('employee_number')->get(),
            'shifts' => Shift::query()->active()->orderBy('start_time')->get(),
            'types' => ScheduleChangeType::cases(),
            'prefill' => $request->only(['employee_id', 'work_date']),
        ]);
    }

    public function store(ScheduleChangeRequestForm $request, ManageScheduleChange $changes): RedirectResponse
    {
        try {
            $changes->request($request->validated(), $request->user());
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['type' => $exception->getMessage()]);
        }

        return redirect()->route('admin.schedule-changes.index')->with('status', __('schedules.changes.submitted'));
    }

    public function decide(Request $request, ScheduleChangeRequest $scheduleChange, string $decision, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('decide', $scheduleChange);

        $data = $request->validate(['comment' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $approval = $scheduleChange->latestApprovalRequest;

        try {
            $decision === 'approve'
                ? $approvals->approve($approval, $request->user(), $data['comment'] ?? null)
                : $approvals->reject($approval, $request->user(), (string) $data['comment']);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['comment' => $exception->getMessage()]);
        }

        return back()->with('status', __('schedules.changes.decided', ['status' => $scheduleChange->fresh()->status->label()]));
    }
}
