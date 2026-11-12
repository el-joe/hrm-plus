<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\ReportExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ReportFilterRequest;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reports centre (spec module 16): catalogue, one report screen (filters, sortable paginated table,
 * totals, chart) and exports (Excel, CSV, PDF). Small exports download at once; large ones are
 * queued (GenerateReportExport) and announced in the notification centre. Access: reports.view
 * (+ the report's own requirements); exports: reports.export. Data scope applies everywhere.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewReportsCentre');

        return view('admin.reports.index', [
            'catalogue' => $this->registry->catalogue($request->user()),
            'legacy' => [
                'attendance' => $request->user()->can('viewAttendanceReports') ? route('admin.attendance.reports.index') : null,
                'leave' => $request->user()->can('viewLeaveReports') ? route('admin.leave-reports.index') : null,
            ],
        ]);
    }

    public function show(ReportFilterRequest $request, ReportRunner $runner, ReportFilters $filters): View
    {
        $report = $request->report();
        $values = $request->filters();
        $result = $runner->screen($report, $values, $request->user(), $request->validated('sort'), (string) $request->validated('direction', 'asc'));

        return view('admin.reports.show', [
            'report' => $report,
            'filters' => $values,
            'options' => $filters->options($report),
            'columns' => $runner->columns($report, $request->user()),
            ...$result,
            'canExport' => $request->user()->can('reports.export'),
            'siblings' => $this->registry->catalogue($request->user())->get($report->area(), []),
        ]);
    }

    public function export(ReportFilterRequest $request, ReportRunner $runner, ReportExporter $exporter, AuditLogger $audit): BinaryFileResponse|RedirectResponse
    {
        $report = $request->report();
        $values = $request->filters();
        $format = (string) $request->validated('format');
        $user = $request->user();
        $count = $runner->count($report, $values, $user);

        if ($format === 'pdf' && $count > ReportExporter::pdfMaxRows()) {
            return back()->with('error', __('reports.exports.pdf_too_large', ['rows' => ReportExporter::pdfMaxRows()]));
        }

        if ($count > ReportExporter::queueThreshold($format)) {
            $export = ReportExport::query()->create([
                'user_id' => $user->id, 'report' => $report->key(), 'format' => $format, 'locale' => app()->getLocale(),
                'filters' => $values, 'status' => ReportExportStatus::Queued,
            ]);
            $audit->log('reports.export_queued', $export, meta: ['report' => $report->key(), 'format' => $format, 'rows' => $count, 'filters' => $values]);
            GenerateReportExport::dispatch($export->id);

            return redirect()->route('admin.reports.exports.index')->with('status', __('reports.exports.queued', ['rows' => $count]));
        }

        $path = storage_path('app/private/report-'.uniqid().'.'.$format);
        $rows = $exporter->write($report, $values, $user, $format, $path);
        $audit->log('reports.exported', meta: ['report' => $report->key(), 'format' => $format, 'rows' => $rows, 'filters' => $values]);

        return response()->download($path, $exporter->fileName($report, $values, $format))->deleteFileAfterSend();
    }
}
