<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStatus;
use App\Services\Attendance\AttendanceImporter;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Attendance import: upload -> dry-run report -> draft batch -> the usual
 * confirmation screen (and approval when required).
 */
class AttendanceImportController extends Controller
{
    public function create(): View
    {
        return view('admin.attendance.import', $this->viewData(null, null));
    }

    public function template(Spreadsheet $spreadsheet): BinaryFileResponse
    {
        $path = storage_path('app/private/attendance-import-template-'.uniqid().'.xlsx');
        $spreadsheet->write($path, 'xlsx', AttendanceImporter::COLUMNS, [['EMP-00001', now()->subDay()->format('Y-m-d'), '', '08:00', '16:00', '', '']]);

        return response()->download($path, 'attendance-import-template.xlsx')->deleteFileAfterSend();
    }

    public function analyse(Request $request, AttendanceImporter $importer): View
    {
        $request->validate(['file' => ['required', 'file', 'extensions:xlsx,csv', 'mimes:xlsx,csv,txt', 'max:'.(int) setting('documents.max_upload_size_kb')]]);

        $token = $importer->stash($request->file('file'), $request->user());
        $file = $importer->stashed($token, $request->user());

        return view('admin.attendance.import', [
            ...$this->viewData($importer->analyse($file['path'], $file['extension']), $token),
            'fileName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function store(Request $request, string $token, AttendanceImporter $importer, AuditLogger $audit): View|RedirectResponse
    {
        $user = $request->user();
        $file = $importer->stashed($token, $user);
        $fileName = (string) $request->input('file_name', 'import');

        if ($file === null) {
            return redirect()->route('admin.attendance.import')->with('error', __('attendance.import.expired'));
        }

        $result = $importer->import($file['path'], $file['extension'], $fileName, $user);

        if ($result['batch'] === null) {
            return view('admin.attendance.import', [...$this->viewData($result['report'], $token), 'fileName' => $fileName]);
        }

        $importer->discard($token, $user);
        $audit->log('attendance.import_prepared', $result['batch'], meta: ['rows' => $result['report']['valid'], 'file' => $fileName]);

        return redirect()->route('admin.attendance.batches.show', $result['batch']);
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<string, mixed>
     */
    private function viewData(?array $report, ?string $token): array
    {
        return [
            'columns' => AttendanceImporter::COLUMNS,
            'statuses' => AttendanceStatus::query()->active()->orderBy('sort_order')->get(),
            'report' => $report,
            'token' => $token,
            'fileName' => null,
        ];
    }
}
