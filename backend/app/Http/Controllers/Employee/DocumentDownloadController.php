<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\PortalProfile;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An employee downloads one of their own documents that HR shared with
 * them. Any other id (someone else's, not shared, deleted) is a 404.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, int $document, PortalProfile $profile, EmployeeFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        $employee = $profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        $record = $profile->documents($employee)->findOrFail($document);
        $log->record(SensitiveDataAccessLog::DOCUMENT, $record, ['file'], ['employee_id' => $employee->getKey(), 'own' => true], $request->user());

        return $files->response($record->file_path, $record->file_name, $record->file_mime);
    }
}
