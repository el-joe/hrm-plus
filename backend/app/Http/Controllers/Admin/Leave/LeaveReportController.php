<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\Spreadsheet;
use App\Services\Leave\LeaveReports;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Leave reports: balances, requests, usage by type / department, upcoming
 * leave, employees on leave — filters, paginated table, Excel / CSV export.
 * Open to leaves.view or reports.view; export to leaves.export or
 * reports.export (the balances report also to leave_balances.export).
 */
class LeaveReportController extends Controller
{
    public function __construct(private readonly LeaveReports $reports) {}

    public function index(): View
    {
        Gate::authorize('viewLeaveReports');

        return view('admin.leave.reports.index', ['reports' => LeaveReports::REPORTS]);
    }

    public function show(Request $request, string $report): View
    {
        Gate::authorize('viewLeaveReports');
        abort_unless(in_array($report, LeaveReports::REPORTS, true), 404);

        $filters = $this->filters($request, $report);

        return view('admin.leave.reports.show', [
            'report' => $report,
            'reports' => LeaveReports::REPORTS,
            'filters' => $filters,
            'columns' => $this->reports->columns($report),
            'numeric' => $this->reports->numeric($report),
            'rows' => $this->page($report, $filters),
            'canExport' => $this->canExport($request->user(), $report),
            'types' => LeaveType::query()->ordered()->get(),
            'statuses' => LeaveRequestStatus::cases(),
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request, string $report, Spreadsheet $spreadsheet, AuditLogger $audit): BinaryFileResponse
    {
        abort_unless(in_array($report, LeaveReports::REPORTS, true), 404);
        abort_unless($this->canExport($request->user(), $report), 403);

        $filters = $this->filters($request, $report);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/leave-report-'.uniqid().'.'.$format);
        $count = 0;

        $rows = (function () use ($report, $filters, &$count): \Generator {
            foreach ($this->reports->query($report, $filters)->lazy(500)->chunk(500) as $chunk) {
                if ($report === 'balances') {
                    $this->reports->primeBalances($chunk->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(), (int) $filters['year']);
                }

                foreach ($chunk as $record) {
                    $count++;

                    yield array_values($this->reports->row($report, $record, $filters));
                }
            }
        })();

        $spreadsheet->write($path, $format, array_values($this->reports->columns($report)), $rows);
        $audit->log('leave.report_exported', meta: ['report' => $report, 'format' => $format, 'rows' => $count, ...array_filter($filters)]);

        return response()->download($path, "leave-{$report}-".now()->format('Ymd').".{$format}")->deleteFileAfterSend();
    }

    private function canExport(User $user, string $report): bool
    {
        return Gate::forUser($user)->allows('exportLeaveReports') || ($report === 'balances' && $user->can('leave_balances.export'));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, string $report): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'leave_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(LeaveRequestStatus::class)],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->reports->withDefaults($report, array_filter($filters, fn ($value): bool => $value !== null && $value !== ''));
    }

    /**
     * One screen page; the balances report loads the page's balances in one go.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function page(string $report, array $filters): LengthAwarePaginator
    {
        $page = $this->reports->query($report, $filters)->paginate(25)->withQueryString();

        if ($report === 'balances') {
            $this->reports->primeBalances($page->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all(), (int) $filters['year']);
        }

        return $page->through(fn ($record): array => $this->reports->row($report, $record, $filters));
    }
}
