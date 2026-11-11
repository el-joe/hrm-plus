<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Employees\EmployeeDirectory;
use App\Services\Employees\EmployeeExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export of the filtered directory (employees.export). Data scopes apply;
 * protected columns follow the exporter's permissions; logged.
 */
class EmployeeExportController extends Controller
{
    public function __invoke(Request $request, EmployeeDirectory $directory, EmployeeExporter $exporter): BinaryFileResponse
    {
        Gate::authorize('export', Employee::class);

        $filters = $request->validate([...$directory->rules(), 'format' => ['nullable', 'in:xlsx,csv']]);
        $format = $filters['format'] ?? 'xlsx';
        unset($filters['format']);

        $path = $exporter->export($directory->query($filters), $request->user(), $format, $filters);

        return response()->download($path, 'employees-'.now()->format('Ymd-His').'.'.$format)->deleteFileAfterSend();
    }
}
