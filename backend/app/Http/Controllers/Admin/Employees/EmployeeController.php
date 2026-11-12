<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\UpdateEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Employees\EmployeeDirectory;
use App\Services\Employees\EmployeeFormOptions;
use App\Services\Employees\EmployeeProfile;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Employee directory, create / edit and the tabbed profile. Placement
 * changes, terminations, documents, contracts and finances have their own
 * controllers; every record goes through EmployeePolicy (permission + data scope).
 */
class EmployeeController extends Controller
{
    public const PER_PAGE = 25;

    public const TABS = ['personal', 'employment', 'contracts', 'documents', 'financial', 'compensation', 'leave', 'performance', 'training', 'assets', 'disciplinary', 'history'];

    public function index(Request $request, EmployeeDirectory $directory, EmployeeFormOptions $options): View
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $request->validate($directory->rules());
        $query = $directory->query($filters)->with(['branch', 'department', 'jobTitle', 'employmentType', 'manager']);

        $sort = IndexSorting::apply($query, $request, [
            'number' => 'employee_number',
            'name' => Employee::localizedColumn(),
            'hire_date' => 'hire_date',
            'status' => 'status',
            'department' => fn (Builder $query, string $direction) => $query->orderBy(
                Department::query()->withoutGlobalScopes()->select(Department::localizedColumn())->whereColumn('departments.id', 'employees.department_id'),
                $direction,
            ),
        ], default: 'number');

        return view('admin.employees.index', [
            'employees' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'options' => $options->filters(),
            'bulk' => $request->user()->can('bulkUpdate', Employee::class) ? $options->bulk() : null,
        ]);
    }

    public function create(EmployeeFormOptions $options): View
    {
        Gate::authorize('create', Employee::class);

        return view('admin.employees.create', [
            'employee' => new Employee(['nationality' => null]),
            'options' => $options->form(null),
        ]);
    }

    public function store(EmployeeRequest $request, CreateEmployee $create): RedirectResponse
    {
        $employee = $create->handle($request->validated(), $request->file('photo'));

        return redirect()->route('admin.employees.show', $employee)
            ->with('status', __('employees.messages.created', ['name' => $employee->name, 'number' => $employee->employee_number]));
    }

    public function show(Request $request, Employee $employee, EmployeeProfile $profile, string $tab = 'personal'): View
    {
        Gate::authorize('view', $employee);

        abort_if(in_array($tab, ['financial', 'compensation'], true) && ! $request->user()->can('viewFinancial', $employee), 403);
        abort_if($tab === 'documents' && ! $request->user()->can('viewDocuments', $employee), 403);
        abort_if($tab === 'leave' && ! $request->user()->can('leaves.view') && ! $request->user()->can('leave_balances.view'), 403);
        abort_if(! $profile->moduleTabVisible($request->user(), $employee, $tab), 403);

        return view('admin.employees.show', [
            'employee' => $employee,
            'tab' => $tab,
            'tabs' => $profile->tabs($request->user(), $employee, $tab),
            ...$profile->tabData($request->user(), $employee, $tab, $request->boolean('reveal')),
        ]);
    }

    public function edit(Employee $employee, EmployeeFormOptions $options): View
    {
        Gate::authorize('update', $employee);

        return view('admin.employees.edit', [
            'employee' => $employee->load(['emergencyContacts', 'groups']),
            'options' => $options->form($employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee, UpdateEmployee $update): RedirectResponse
    {
        $update->handle($employee, $request->validated(), $request->file('photo'));

        return redirect()->route('admin.employees.show', $employee)->with('status', __('employees.messages.updated', ['name' => $employee->name]));
    }
}
