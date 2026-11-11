<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Leave\ManageLeaveRequest;
use App\Enums\LeaveDayPart;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\OwnLeaveRequest;
use App\Models\Employee;
use App\Services\Attendance\BulkAttendance;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\PortalProfile;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveEligibility;
use App\Services\Leave\LeaveYear;
use App\Services\Leave\PortalLeave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "My leaves" (spec section 5): balances, new request with attachments,
 * history with status, cancellation where the rules permit. Own record only.
 */
class LeaveController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalLeave $leave) {}

    public function index(Request $request, LeaveBalanceCalculator $balances, LeaveEligibility $eligibility, LeaveYear $years): View
    {
        $employee = $this->employee($request);
        $types = $eligibility->requestableTypes($employee);

        return view('employee.leaves', [
            'employee' => $employee,
            'yearLabel' => $years->label($years->current()),
            'balances' => $balances->all($employee, null, $types),
            'types' => $types,
            'parts' => LeaveDayPart::cases(),
            'allowHalfDay' => (bool) setting('leave.allow_half_day'),
            'requests' => $this->leave->paginate($employee, 15),
            'today' => BulkAttendance::today(),
        ]);
    }

    public function store(OwnLeaveRequest $request, ManageLeaveRequest $requests): RedirectResponse
    {
        $leave = $requests->submit($this->employee($request), $request->leaveData(), LeaveRequestSource::Portal, $request->user(), $request->attachmentFiles());

        return redirect()->route('portal.leaves')->with('status', __($leave->status === LeaveRequestStatus::Approved ? 'leave.portal.approved_now' : 'leave.portal.sent'));
    }

    public function cancel(Request $request, int $leaveRequest, ManageLeaveRequest $requests): RedirectResponse
    {
        $record = $this->leave->find($this->employee($request), $leaveRequest);
        abort_if($record === null, 404);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;
        $status = $requests->cancel($record, $request->user(), $reason);

        return back()->with('status', __($status === LeaveRequestStatus::CancellationPending ? 'leave.portal.cancellation_requested' : 'leave.portal.cancelled'));
    }

    public function attachment(Request $request, int $leaveRequest, int $attachment, EmployeeFileStore $files): StreamedResponse
    {
        $file = $this->leave->attachment($this->employee($request), $leaveRequest, $attachment);
        abort_if($file === null, 404);

        return $files->response($file->file_path, $file->file_name, $file->file_mime);
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
