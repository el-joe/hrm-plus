<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Services\Attendance\Reports\AttendanceReports;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Attendance reports: daily, monthly summary, lateness, absence, overtime,
 * manual entries, device sync — filters, paginated table, Excel / CSV export.
 * Open to attendance.view or reports.view; export to attendance.export or
 * reports.export. Data scope applies to the screen and the export alike.
 */
class AttendanceReportController extends Controller
{
    public function __construct(private readonly AttendanceReports $reports) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAttendanceReports');

        return view('admin.attendance.reports.index', ['reports' => $this->reports->available($request->user())]);
    }

    public function show(Request $request, string $report): View
    {
        Gate::authorize('viewAttendanceReports');
        abort_unless(in_array($report, $this->reports->available($request->user()), true), 404);

        $filters = $this->filters($request);
        $page = $this->reports->query($report, $filters)->paginate(25)->withQueryString();

        return view('admin.attendance.reports.show', [
            'report' => $report,
            'reports' => $this->reports->available($request->user()),
            'filters' => $filters,
            'columns' => $this->reports->columns($report),
            'numeric' => $this->reports->numeric($report),
            'rows' => $page->through(fn ($record): array => $this->reports->row($report, $record)),
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request, string $report, Spreadsheet $spreadsheet, AuditLogger $audit): BinaryFileResponse
    {
        Gate::authorize('exportAttendanceReports');
        abort_unless(in_array($report, $this->reports->available($request->user()), true), 404);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/attendance-report-'.uniqid().'.'.$format);
        $count = 0;

        $rows = (function () use ($report, $filters, &$count): \Generator {
            foreach ($this->reports->query($report, $filters)->lazy(500) as $record) {
                $count++;

                yield array_values($this->reports->row($report, $record));
            }
        })();

        $spreadsheet->write($path, $format, array_values($this->reports->columns($report)), $rows);
        $audit->log('attendance.report_exported', meta: ['report' => $report, 'format' => $format, 'rows' => $count, ...array_filter($filters)]);

        return response()->download($path, "attendance-{$report}-{$filters['from']}-{$filters['to']}.{$format}")->deleteFileAfterSend();
    }

    /**
     * @return array{from: string, to: string, branch_id?: ?int, department_id?: ?int, search?: ?string}
     */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        [$from, $to] = AttendanceReports::defaultRange();
        $filters['from'] ??= $from->format('Y-m-d');
        $filters['to'] ??= max($to->format('Y-m-d'), $filters['from']);

        if (CarbonImmutable::parse($filters['from'])->diffInDays(CarbonImmutable::parse($filters['to'])) > AttendanceReports::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => __('attendance_reports.range_too_long', ['days' => AttendanceReports::MAX_RANGE_DAYS])]);
        }

        return $filters;
    }
}
