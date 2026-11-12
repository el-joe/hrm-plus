<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceLogImporter;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CSV / Excel fallback import of a device's exported log: upload -> dry run
 * (nothing written) -> import.
 */
class DeviceImportController extends Controller
{
    public function create(AttendanceDevice $attendanceDevice): View
    {
        Gate::authorize('manage', $attendanceDevice);

        return view('admin.attendance.devices.import', ['device' => $attendanceDevice, 'report' => null, 'token' => null, 'fileName' => null, 'columns' => DeviceLogImporter::COLUMNS]);
    }

    public function template(AttendanceDevice $attendanceDevice, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('manage', $attendanceDevice);

        $path = storage_path('app/private/device-log-template-'.uniqid().'.xlsx');
        $spreadsheet->write($path, 'xlsx', DeviceLogImporter::COLUMNS, [['1024', now()->subDay()->format('Y-m-d').' 08:02:10', 'in', '']]);

        return response()->download($path, 'device-log-template.xlsx')->deleteFileAfterSend();
    }

    public function analyse(Request $request, AttendanceDevice $attendanceDevice, DeviceLogImporter $importer): View
    {
        Gate::authorize('manage', $attendanceDevice);
        $request->validate(['file' => ['required', 'file', 'extensions:xlsx,csv,txt', 'mimes:xlsx,csv,txt', 'max:'.(int) setting('documents.max_upload_size_kb')]]);

        $token = $importer->stash($request->file('file'), $request->user());
        $file = $importer->stashed($token, $request->user());

        return view('admin.attendance.devices.import', [
            'device' => $attendanceDevice,
            'report' => $importer->analyse($attendanceDevice, $file['path'], $file['extension']),
            'token' => $token,
            'fileName' => $request->file('file')->getClientOriginalName(),
            'columns' => DeviceLogImporter::COLUMNS,
        ]);
    }

    public function store(Request $request, AttendanceDevice $attendanceDevice, string $token, DeviceLogImporter $importer, AuditLogger $audit): View|RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);

        $user = $request->user();
        $file = $importer->stashed($token, $user);
        $fileName = (string) $request->input('file_name', 'device-log');

        if ($file === null) {
            return redirect()->route('admin.attendance-devices.import', $attendanceDevice)->with('error', __('devices.import.expired'));
        }

        $result = $importer->import($attendanceDevice, $file['path'], $file['extension'], $fileName, $user);

        if ($result['log'] === null) {
            return view('admin.attendance.devices.import', ['device' => $attendanceDevice, 'report' => $result['report'], 'token' => $token, 'fileName' => $fileName, 'columns' => DeviceLogImporter::COLUMNS]);
        }

        $importer->discard($token, $user);
        $audit->log('attendance_device.file_imported', $attendanceDevice, meta: ['sync_log_id' => $result['log']->id, 'file' => $fileName, 'created' => $result['log']->created]);

        return redirect()->route('admin.attendance-devices.show', $attendanceDevice)
            ->with('status', __('devices.flash.synced', ['created' => $result['log']->created, 'duplicates' => $result['log']->duplicates, 'unmatched' => $result['log']->unmatched]));
    }
}
