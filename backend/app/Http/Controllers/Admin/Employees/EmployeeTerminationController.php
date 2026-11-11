<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\RehireEmployee;
use App\Actions\Employees\TerminateEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\RehireEmployeeRequest;
use App\Http\Requests\Admin\Employees\TerminateEmployeeRequest;
use App\Models\Employee;
use App\Services\Employees\EmployeeFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Terminate and rehire (employees.manage). Both are history entries; the
 * employee record, documents, contracts and salary history are kept.
 */
class EmployeeTerminationController extends Controller
{
    public function create(Employee $employee, EmployeeFormOptions $options): View
    {
        Gate::authorize('manage', $employee);
        abort_if($employee->isTerminated(), 404);

        return view('admin.employees.terminate', ['employee' => $employee, 'options' => $options->form($employee)]);
    }

    public function store(TerminateEmployeeRequest $request, Employee $employee, TerminateEmployee $terminate): RedirectResponse
    {
        $assignment = $terminate->handle($employee, $request->validated());

        return redirect()->route('admin.employees.tab', [$employee, 'employment'])->with('status', __(
            $assignment->isPending() ? 'employees.messages.termination_scheduled' : 'employees.messages.terminated',
            ['name' => $employee->name, 'date' => $assignment->effective_date->toDateString()],
        ));
    }

    public function rehireForm(Employee $employee, EmployeeFormOptions $options): View
    {
        Gate::authorize('manage', $employee);
        abort_unless($employee->isTerminated(), 404);

        return view('admin.employees.rehire', ['employee' => $employee, 'options' => $options->form($employee)]);
    }

    public function rehire(RehireEmployeeRequest $request, Employee $employee, RehireEmployee $rehire): RedirectResponse
    {
        $rehire->handle($employee, $request->placement(), $request->validated('rehire_date'), $request->validated('reason'));

        return redirect()->route('admin.employees.tab', [$employee, 'employment'])->with('status', __('employees.messages.rehired', ['name' => $employee->name]));
    }
}
