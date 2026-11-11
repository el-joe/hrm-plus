<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendanceTimesheet;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\Spreadsheet;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Monthly timesheet of one employee: every date with its expected schedule,
 * the processed day(s) and the month totals.
 */
class TimesheetController extends Controller
{
    public function index(Request $request, AttendanceTimesheet $timesheet): View
    {
        [$employee, $from, $to, $month] = $this->input($request);
        $rows = $employee ? $timesheet->rows($employee, $from, $to) : [];

        return view('admin.attendance.timesheet', [
            'employee' => $employee,
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'month' => $month,
            'from' => $from,
            'rows' => $rows,
            'summary' => $employee ? $timesheet->summary(collect($rows)->flatMap(fn (array $row): array => $row['days'])) : null,
        ]);
    }

    public function export(Request $request, AttendanceTimesheet $timesheet, Spreadsheet $spreadsheet, AuditLogger $audit): BinaryFileResponse
    {
        [$employee, $from, $to, $month] = $this->input($request);
        abort_if($employee === null, 404);

        $path = storage_path('app/private/timesheet-'.uniqid().'.xlsx');
        $lines = [];

        foreach ($timesheet->rows($employee, $from, $to) as $row) {
            foreach ($row['days'] ?: [null] as $day) {
                $lines[] = [
                    $row['date']->format('Y-m-d'), $day?->shift?->code,
                    AttendanceClock::localTime($day?->first_in_at, $row['schedule']->timezone), AttendanceClock::localTime($day?->last_out_at, $row['schedule']->timezone),
                    Duration::format($day?->worked_minutes, ''), $day?->late_minutes, $day?->early_leave_minutes, $day?->overtime_minutes, $day?->overtime_approved_minutes,
                    $day?->statusLabel(), $day?->is_manual ? __('attendance.common.manual') : '',
                ];
            }
        }

        $spreadsheet->write($path, 'xlsx', [
            __('attendance.fields.date'), __('attendance.fields.shift'), __('attendance.fields.check_in'), __('attendance.fields.check_out'),
            __('attendance.fields.worked'), __('attendance.fields.late'), __('attendance.fields.early_leave'), __('attendance.fields.overtime'),
            __('attendance.fields.overtime_approved'), __('attendance.fields.status'), __('attendance.fields.source'),
        ], $lines);

        $audit->log('attendance.timesheet_exported', $employee, meta: ['month' => $month]);

        return response()->download($path, "timesheet-{$employee->employee_number}-{$month}.xlsx")->deleteFileAfterSend();
    }

    /**
     * @return array{0: ?Employee, 1: CarbonImmutable, 2: CarbonImmutable, 3: string}
     */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        [$from, $to] = AttendanceTimesheet::month($data['month'] ?? null);
        $employee = isset($data['employee_id']) ? Employee::query()->find($data['employee_id']) : null;

        abort_if(isset($data['employee_id']) && $employee === null, 404);

        return [$employee, $from, $to, $from->format('Y-m')];
    }
}
