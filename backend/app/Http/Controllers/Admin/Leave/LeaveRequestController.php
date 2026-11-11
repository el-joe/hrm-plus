<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Actions\Leave\ManageLeaveRequest;
use App\Enums\LeaveDayPart;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\StoreLeaveRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Approvals\ApprovalService;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leave requests queue (filters, "awaiting my approval", inline approve /
 * reject through the approval engine), request details with approval
 * history, balance and team conflicts, requests on an employee's behalf and
 * cancellation by HR.
 */
class LeaveRequestController extends Controller
{
    public function __construct(private readonly ManageLeaveRequest $requests) {}

    public function index(Request $request, ApprovalService $approvals): View
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(LeaveRequestStatus::class)],
            'leave_type_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $requests = LeaveRequest::query()
            ->with(['employee.department', 'leaveType', 'requester', 'latestApprovalRequest.currentStep', 'latestApprovalRequest.workflow'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['leave_type_id'] ?? null, fn (Builder $query, int $id) => $query->where('leave_type_id', $id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('employee', fn (Builder $query) => $query->where('branch_id', $id)))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('employee', fn (Builder $query) => $query->where('department_id', $id)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $query) => $query->search($search)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('start_date', '<=', $to))
            ->when($request->boolean('mine'), fn (Builder $query) => $query->whereIn('id', $approvals->pendingFor($request->user())
                ->where('approvable_type', (new LeaveRequest)->getMorphClass())->select('approvable_id')))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.leave.requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'statuses' => LeaveRequestStatus::cases(),
            'types' => LeaveType::query()->ordered()->get(),
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function create(Request $request, LeaveBalanceCalculator $balances): View
    {
        Gate::authorize('create', LeaveRequest::class);

        $employee = $request->integer('employee_id') ? Employee::query()->employed()->find($request->integer('employee_id')) : null;

        return view('admin.leave.requests.create', [
            'employee' => $employee,
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'types' => LeaveType::query()->active()->ordered()->get(),
            'parts' => LeaveDayPart::cases(),
            'balances' => $employee ? $balances->all($employee) : collect(),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $leave = $this->requests->submit(
            $request->employee(), $request->leaveData(), LeaveRequestSource::Admin, $request->user(),
            $request->attachmentFiles(), $request->boolean('record_as_approved'),
        );

        return redirect()->route('admin.leave-requests.show', $leave)
            ->with('status', __($leave->status === LeaveRequestStatus::Approved ? 'leave.requests.recorded' : 'leave.requests.submitted'));
    }

    public function show(LeaveRequest $leaveRequest, LeaveBalanceCalculator $balances, LeaveCalendar $calendar): View
    {
        Gate::authorize('view', $leaveRequest);

        $leaveRequest->load(['employee.department', 'leaveType', 'requester', 'decider', 'requestDays', 'attachments.uploader', 'approvalRequests.actions.actor', 'approvalRequests.workflow']);

        return view('admin.leave.requests.show', [
            'leave' => $leaveRequest,
            'balance' => $leaveRequest->leaveType->tracks_balance ? $balances->for($leaveRequest->employee, $leaveRequest->leaveType, $leaveRequest->requestDays->first()?->leave_year) : null,
            'conflicts' => $calendar->conflictsFor($leaveRequest),
            'canDecide' => Gate::allows('decide', $leaveRequest),
            'canCancel' => Gate::allows('cancel', $leaveRequest),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leaveRequest, string $decision): RedirectResponse
    {
        Gate::authorize('decide', $leaveRequest);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $this->requests->decide($leaveRequest, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', __('leave.requests.'.($decision === 'approve' ? 'approved' : 'rejected')));
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('cancel', $leaveRequest);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->requests->cancel($leaveRequest, $request->user(), $data['reason'], asAdmin: true);

        return redirect()->route('admin.leave-requests.show', $leaveRequest)->with('status', __('leave.requests.cancelled'));
    }

    public function attachment(LeaveRequest $leaveRequest, int $attachment, EmployeeFileStore $files): StreamedResponse
    {
        Gate::authorize('view', $leaveRequest);

        $file = $leaveRequest->attachments()->findOrFail($attachment);

        return $files->response($file->file_path, $file->file_name, $file->file_mime);
    }
}
