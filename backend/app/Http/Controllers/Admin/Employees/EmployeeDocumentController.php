<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\ManageEmployeeDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee documents (employee_documents.*). Files live on the private disk;
 * download() is the only way to read one: permission + data scope are
 * checked and every download is written to the sensitive-data access log.
 */
class EmployeeDocumentController extends Controller
{
    public function store(DocumentRequest $request, Employee $employee, ManageEmployeeDocuments $documents): RedirectResponse
    {
        $documents->store($employee, $request->validated(), $request->file('file'));

        return redirect()->route('admin.employees.tab', [$employee, 'documents'])->with('status', __('employees.messages.document_uploaded'));
    }

    public function update(DocumentRequest $request, Employee $employee, EmployeeDocument $document, ManageEmployeeDocuments $documents): RedirectResponse
    {
        $documents->update($document, $request->validated());

        return redirect()->route('admin.employees.tab', [$employee, 'documents'])->with('status', __('employees.messages.document_updated'));
    }

    public function destroy(Employee $employee, EmployeeDocument $document, ManageEmployeeDocuments $documents): RedirectResponse
    {
        Gate::authorize('deleteDocuments', $employee);

        $documents->delete($document);

        return redirect()->route('admin.employees.tab', [$employee, 'documents'])->with('status', __('employees.messages.document_deleted', ['title' => $document->title]));
    }

    public function download(Request $request, Employee $employee, EmployeeDocument $document, EmployeeFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('viewDocuments', $employee);

        $log->record(SensitiveDataAccessLog::DOCUMENT, $document, ['file'], ['employee_id' => $employee->getKey(), 'category' => $document->category->value], $request->user());

        return $files->response($document->file_path, $document->file_name, $document->file_mime);
    }
}
