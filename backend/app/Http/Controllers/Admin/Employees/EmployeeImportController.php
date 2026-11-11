<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\ImportEmployeesRequest;
use App\Models\Employee;
use App\Services\Employees\EmployeeImporter;
use App\Services\Employees\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel / CSV import: upload -> dry-run report (nothing saved) -> confirm
 * (all rows or none). See App\Services\Employees\EmployeeImporter.
 */
class EmployeeImportController extends Controller
{
    public function create(Request $request, EmployeeImporter $importer): View
    {
        Gate::authorize('import', Employee::class);

        return view('admin.employees.import', [
            'columns' => $importer->columns($request->user()),
            'report' => null,
            'token' => null,
        ]);
    }

    public function template(Request $request, EmployeeImporter $importer, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('import', Employee::class);

        $path = storage_path('app/private/employee-import-template-'.uniqid().'.xlsx');
        $spreadsheet->write($path, 'xlsx', $importer->columns($request->user()), []);

        return response()->download($path, 'employee-import-template.xlsx')->deleteFileAfterSend();
    }

    /**
     * Step 1: keep the file and show the dry-run report.
     */
    public function analyse(ImportEmployeesRequest $request, EmployeeImporter $importer): View
    {
        $user = $request->user();
        $token = $importer->stash($request->file('file'), $user);
        $file = $importer->stashed($token, $user);

        return view('admin.employees.import', [
            'columns' => $importer->columns($user),
            'report' => $importer->analyse($file['path'], $file['extension'], $user),
            'token' => $token,
            'fileName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    /**
     * Step 2: import the analysed file.
     */
    public function store(Request $request, string $token, EmployeeImporter $importer): View|RedirectResponse
    {
        Gate::authorize('import', Employee::class);

        $user = $request->user();
        $file = $importer->stashed($token, $user);

        if ($file === null) {
            return redirect()->route('admin.employees.import')->with('error', __('employees.import.expired'));
        }

        $report = $importer->import($file['path'], $file['extension'], $user);

        if ($report['created'] === 0) {
            return view('admin.employees.import', [
                'columns' => $importer->columns($user),
                'report' => $report,
                'token' => $token,
                'fileName' => null,
            ]);
        }

        $importer->discard($token, $user);

        return redirect()->route('admin.employees.index')->with('status', trans_choice('employees.import.done', $report['created'], ['count' => $report['created']]));
    }
}
