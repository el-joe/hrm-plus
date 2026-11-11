<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Enums\RawEventSource;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Department;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\BulkAttendance;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\Spreadsheet;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Daily attendance: processed days of one date, filterable by branch,
 * department, status and source, with the counts per status. Rows open the
 * day breakdown. Data scope applies (AttendanceDay uses HasDataScope).
 */
class DailyAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);

        $counts = $this->base($filters)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n): int => (int) $n);
        $manual = $this->base($filters)->where('is_manual', true)->count();

        return view('admin.attendance.daily', [
            'filters' => $filters,
            'days' => $query->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('attendance_days.status', $status))
                ->paginate(25)->withQueryString(),
            'counts' => $counts,
            'manualCount' => $manual,
            'statuses' => AttendanceDayStatus::cases(),
            'sources' => RawEventSource::cases(),
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request, Spreadsheet $spreadsheet, AuditLogger $audit): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $days = $this->query($filters)->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('attendance_days.status', $status))->get();
        $path = storage_path('app/private/attendance-'.uniqid().'.xlsx');

        $spreadsheet->write($path, 'xlsx', [
            __('attendance.fields.employee_number'), __('attendance.fields.employee'), __('attendance.fields.date'), __('attendance.fields.shift'),
            __('attendance.fields.check_in'), __('attendance.fields.check_out'), __('attendance.fields.worked'), __('attendance.fields.late'),
            __('attendance.fields.early_leave'), __('attendance.fields.overtime'), __('attendance.fields.status'), __('attendance.fields.source'),
        ], $days->map(fn (AttendanceDay $day): array => [
            $day->employee->employee_number, $day->employee->name, $day->work_date->format('Y-m-d'), $day->shift?->code,
            AttendanceClock::localTime($day->first_in_at, $day->timezone), AttendanceClock::localTime($day->last_out_at, $day->timezone),
            Duration::format($day->worked_minutes, ''), $day->late_minutes, $day->early_leave_minutes, $day->overtime_minutes,
            $day->statusLabel(), implode(', ', array_map(fn (string $source): string => RawEventSource::from($source)->label(), $day->sources ?? [])),
        ]));

        $audit->log('attendance.exported', meta: ['date' => $filters['date'], 'rows' => $days->count()]);

        return response()->download($path, 'attendance-'.$filters['date'].'.xlsx')->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AttendanceDayStatus::class)],
            'source' => ['nullable', Rule::enum(RawEventSource::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['date'] ??= BulkAttendance::today()->format('Y-m-d');

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDay>
     */
    private function query(array $filters): Builder
    {
        return $this->base($filters)
            ->with(['employee', 'shift', 'attendanceStatus', 'branch', 'department'])
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->select('attendance_days.*')
            ->orderBy('employees.employee_number')->orderBy('attendance_days.scheduled_start_at');
    }

    /**
     * The date and filters, without status (the counts show every status).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDay>
     */
    private function base(array $filters): Builder
    {
        return AttendanceDay::query()
            ->whereDate('attendance_days.work_date', $filters['date'])
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('attendance_days.branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('attendance_days.department_id', $id))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->whereJsonContains('attendance_days.sources', $source))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $query) => $query->search($search)));
    }
}
