<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\DepartmentRequest;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DepartmentController extends StructureController
{
    private const ID_FILTERS = ['branch_id', 'parent_id', 'cost_center_id'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Department::class);

        $filters = $this->filters($request, self::ID_FILTERS);
        $query = $this->applyFilters(
            Department::query()->with(['parent', 'branch', 'headPosition'])->withCount(['positions', 'teams'])->withSum('positions', 'headcount'),
            $filters,
            self::ID_FILTERS,
        );
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => Department::localizedColumn(),
            'positions' => fn (Builder $query, string $direction) => $query->orderBy('positions_count', $direction),
            'branch' => fn (Builder $query, string $direction) => $query->orderBy(
                Branch::query()->select(Branch::localizedColumn())->whereColumn('branches.id', 'departments.branch_id'), $direction,
            ),
        ], default: 'code');

        return view('admin.structure.departments.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'branches' => Branch::query()->orderBy('code')->get(),
            'parents' => Department::query()->has('children')->orderBy('code')->get(),
            'costCenters' => CostCenter::query()->orderBy('code')->get(),
        ]);
    }

    public function show(Department $department): View
    {
        Gate::authorize('view', $department);

        return view('admin.structure.departments.show', [
            'department' => $department->load(['parent', 'branch', 'costCenter', 'headPosition.jobTitle']),
            'children' => $department->children()->with('branch')->withCount('positions')->orderBy('code')->get(),
            'teams' => $department->teams()->orderBy('code')->get(),
            'positions' => $department->positions()->with(['jobTitle', 'jobGrade', 'reportsTo'])->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return $this->form(new Department(['is_active' => true]));
    }

    public function store(DepartmentRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $department = $save->handle(new Department, $request->recordData());

        return redirect()->route('admin.departments.show', $department)->with('status', __('structure.messages.created', ['name' => $department->name]));
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return $this->form($department);
    }

    public function update(DepartmentRequest $request, Department $department, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($department, $request->recordData());

        return redirect()->route('admin.departments.show', $department)->with('status', __('structure.messages.updated', ['name' => $department->name]));
    }

    public function destroy(Department $department, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($department, $delete, 'admin.departments.index');
    }

    public function status(Request $request, Department $department, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $department, $changeStatus);
    }

    private function form(Department $department): View
    {
        return $this->formPage($department, 'departments', 'departments', [
            'cancelUrl' => $department->exists ? route('admin.departments.show', $department) : route('admin.departments.index'),
            'parents' => Department::query()->activeOrKeep($department->parent_id)
                ->whereKeyNot($department->getKey() ?? 0)->whereKeyNot($department->exists ? $department->descendantIds() : [])
                ->orderBy('code')->get(),
            'branches' => Branch::query()->activeOrKeep($department->branch_id)->orderBy('code')->get(),
            'costCenters' => CostCenter::query()->activeOrKeep($department->cost_center_id)->orderBy('code')->get(),
            'headPositions' => $department->exists
                ? $department->positions()->activeOrKeep($department->head_position_id)->orderBy('code')->get()
                : collect(),
        ]);
    }
}
