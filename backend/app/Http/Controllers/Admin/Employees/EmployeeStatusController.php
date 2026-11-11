<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\ChangeEmployeeStatus;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Activate / deactivate an employed employee (a status history entry).
 */
class EmployeeStatusController extends Controller
{
    public function __invoke(Request $request, Employee $employee, ChangeEmployeeStatus $changeStatus): RedirectResponse
    {
        Gate::authorize('update', $employee);
        abort_if($employee->isTerminated(), 422);

        $data = $request->validate([
            'status' => ['required', Rule::in([EmployeeStatus::Active->value, EmployeeStatus::Inactive->value])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $changeStatus->handle($employee, EmployeeStatus::from($data['status']), $data['reason'] ?? null);

        return back()->with('status', __($data['status'] === 'active' ? 'employees.messages.activated' : 'employees.messages.deactivated', ['name' => $employee->name]));
    }
}
