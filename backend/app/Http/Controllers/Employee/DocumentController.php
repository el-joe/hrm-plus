<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\PortalDocuments;
use App\Services\Employees\PortalProfile;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal "My documents": every file about the employee on one page (shared documents, contracts,
 * training certificates, letters issued on HR requests, payslips). Downloads go through the
 * owner-only routes of each module; the contract copy is served here.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalDocuments $documents) {}

    public function index(Request $request): View
    {
        $all = $this->documents->all($this->employee($request));

        return view('employee.documents.index', [
            'groups' => $all,
            'counts' => PortalDocuments::counts($all),
        ]);
    }

    public function contract(Request $request, int $contract, EmployeeFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        $employee = $this->employee($request);
        $record = $this->documents->findContract($employee, $contract);
        abort_if($record === null, 404);
        $log->record(SensitiveDataAccessLog::CONTRACT, $record, ['file'], ['employee_id' => $employee->getKey(), 'own' => true], $request->user());

        return $files->response($record->file_path, $record->file_name, $record->file_mime);
    }

    /**
     * The employee's own photo (inline image).
     */
    public function photo(Request $request, EmployeeFileStore $files): StreamedResponse
    {
        $employee = $this->employee($request);
        abort_if($employee->photo_path === null, 404);
        $extension = strtolower(pathinfo($employee->photo_path, PATHINFO_EXTENSION));

        return $files->response($employee->photo_path, 'photo.'.$extension, 'image/'.($extension === 'jpg' ? 'jpeg' : $extension), inline: true);
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
