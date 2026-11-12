<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "My exports": the signed-in admin's queued report exports and their downloads. Nobody else's
 * exports are ever listed or served (a foreign id is a 404).
 */
class ReportExportController extends Controller
{
    public function index(Request $request, ReportRegistry $registry): View
    {
        Gate::authorize('viewReportsCentre');

        return view('admin.reports.exports', [
            'exports' => ReportExport::query()->where('user_id', $request->user()->id)->latest('id')->paginate(20),
            'registry' => $registry,
        ]);
    }

    public function download(Request $request, ReportExport $export, AuditLogger $audit): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()->id, 404);
        abort_unless($request->user()->can('reports.export') && $export->isDownloadable() && Storage::disk('local')->exists($export->file_path), 404);

        $audit->log('reports.export_downloaded', $export, meta: ['report' => $export->report, 'format' => $export->format]);

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }
}
