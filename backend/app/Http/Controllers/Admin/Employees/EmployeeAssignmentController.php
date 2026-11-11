<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\ChangeEmployeeAssignment;
use App\Enums\AssignmentChangeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\AssignmentChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Employees\EmployeeFormOptions;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Transfer, promote, reassign or change the manager of an employee: a dated
 * history entry, applied now or scheduled. Pending entries can be cancelled.
 */
class EmployeeAssignmentController extends Controller
{
    public function create(Request $request, Employee $employee, EmployeeFormOptions $options): View
    {
        Gate::authorize('update', $employee);
        abort_if($employee->isTerminated(), 404);

        $type = $request->validate(['type' => ['nullable', Rule::in(array_map(fn ($type) => $type->value, AssignmentChangeRequest::TYPES))]])['type'] ?? 'transfer';

        return view('admin.employees.assignment', [
            'employee' => $employee,
            'type' => AssignmentChangeType::from($type),
            'options' => $options->form($employee),
        ]);
    }

    public function store(AssignmentChangeRequest $request, Employee $employee, ChangeEmployeeAssignment $change): RedirectResponse
    {
        $assignment = $change->handle(
            $employee,
            AssignmentChangeType::from($request->validated('change_type')),
            $request->placement(),
            $request->validated('effective_date'),
            $request->validated('reason'),
        );

        return redirect()->route('admin.employees.tab', [$employee, 'employment'])->with('status', __(
            $assignment->isPending() ? 'employees.messages.change_scheduled' : 'employees.messages.change_applied',
            ['type' => $assignment->change_type->label(), 'date' => $assignment->effective_date->toDateString()],
        ));
    }

    public function destroy(Employee $employee, EmployeeAssignment $assignment, EmploymentHistory $history): RedirectResponse
    {
        Gate::authorize($assignment->change_type === AssignmentChangeType::Termination ? 'manage' : 'update', $employee);
        abort_unless($assignment->isPending(), 404);

        $history->cancel($assignment);

        return back()->with('status', __('employees.messages.change_cancelled'));
    }
}
