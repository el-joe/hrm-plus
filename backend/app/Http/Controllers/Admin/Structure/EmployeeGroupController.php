<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\EmployeeGroupRequest;
use App\Models\EmployeeGroup;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Employee groups: free groupings used for admin data restrictions and bulk
 * selection. Members are added from the employee list (bulk action) or the
 * employee form.
 */
class EmployeeGroupController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmployeeGroup::class);

        $filters = $this->filters($request);
        // Member counts follow the admin's data scope (only employees they can see).
        $query = $this->applyFilters(EmployeeGroup::query()->withCount('employees'), $filters);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => EmployeeGroup::localizedColumn(),
            'employees' => fn (Builder $query, string $direction) => $query->orderBy('employees_count', $direction),
        ], default: 'code');

        return view('admin.structure.employee-groups.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', EmployeeGroup::class);

        return $this->form(new EmployeeGroup(['is_active' => true]));
    }

    public function store(EmployeeGroupRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $group = $save->handle(new EmployeeGroup, $request->recordData());

        return redirect()->route('admin.employee-groups.index')->with('status', __('structure.messages.created', ['name' => $group->name]));
    }

    public function edit(EmployeeGroup $employeeGroup): View
    {
        Gate::authorize('update', $employeeGroup);

        return $this->form($employeeGroup);
    }

    public function update(EmployeeGroupRequest $request, EmployeeGroup $employeeGroup, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($employeeGroup, $request->recordData());

        return redirect()->route('admin.employee-groups.index')->with('status', __('structure.messages.updated', ['name' => $employeeGroup->name]));
    }

    public function destroy(EmployeeGroup $employeeGroup, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($employeeGroup, $delete, 'admin.employee-groups.index');
    }

    public function status(Request $request, EmployeeGroup $employeeGroup, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $employeeGroup, $changeStatus);
    }

    private function form(EmployeeGroup $group): View
    {
        return $this->formPage($group, 'employee-groups', 'employee_groups', [
            'title' => __('employees.groups.'.($group->exists ? 'edit' : 'create')),
            'breadcrumbs' => [['label' => __('employees.groups.title'), 'url' => route('admin.employee-groups.index')]],
        ]);
    }
}
