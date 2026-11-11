<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\BulkUpdateEmployees;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\BulkEmployeeRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Bulk operations on the employees selected in the directory.
 */
class EmployeeBulkController extends Controller
{
    public function __invoke(BulkEmployeeRequest $request, BulkUpdateEmployees $bulk): RedirectResponse
    {
        $result = $bulk->handle($request->employees(), $request->validated('action'), $request->validated());

        $redirect = back()->with('status', trans_choice('employees.bulk.done', $result['updated'], ['count' => $result['updated']]));

        if ($result['skipped'] !== []) {
            $redirect->with('bulkSkipped', $result['skipped']);
        }

        return $redirect;
    }
}
