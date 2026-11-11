<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Attendance\ManageAttendanceCorrection;
use App\Enums\CorrectionSource;
use App\Exceptions\ApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Attendance\OwnCorrectionRequest;
use App\Models\Employee;
use App\Services\Attendance\AttendanceTimesheet;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\PortalAttendance;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My attendance" (spec section 5): schedule of the coming days, the month's
 * daily records with totals, and correction requests. Own record only.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalAttendance $attendance) {}

    public function index(Request $request, AttendanceTimesheet $timesheet): View
    {
        $employee = $this->employee($request);
        [$from, $to] = AttendanceTimesheet::month($request->query('month'));
        $today = BulkAttendance::today();
        $rows = $timesheet->rows($employee, $from, min($to, $today));

        return view('employee.attendance', [
            'employee' => $employee,
            'from' => $from,
            'rows' => array_reverse($rows),
            'summary' => $timesheet->summary(collect($rows)->flatMap(fn (array $row): array => $row['days'])),
            'upcoming' => $this->attendance->schedule($employee, $today->format('Y-m-d'), $today->addDays(13)->format('Y-m-d')),
            'corrections' => $this->attendance->corrections($employee, 10),
            'statuses' => $this->attendance->requestableStatuses(),
            'today' => $today,
        ]);
    }

    public function storeCorrection(OwnCorrectionRequest $request, ManageAttendanceCorrection $corrections): RedirectResponse
    {
        try {
            $corrections->create($this->employee($request), $request->validated(), CorrectionSource::Portal, $request->user());
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return redirect()->route('portal.attendance', ['month' => substr((string) $request->input('work_date'), 0, 7)])->with('status', __('attendance.portal.request_sent'));
    }

    public function cancelCorrection(Request $request, int $correction, ManageAttendanceCorrection $corrections): RedirectResponse
    {
        $record = $this->attendance->ownCorrection($this->employee($request), $correction);
        abort_if($record === null, 404);

        $corrections->cancel($record, $request->user());

        return back()->with('status', __('attendance.portal.request_withdrawn'));
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
