<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\BranchRequest;
use App\Models\Branch;
use App\Models\Position;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BranchController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Branch::class);

        $filters = $this->filters($request);
        $query = $this->applyFilters(Branch::query()->with('managerPosition')->withCount(['locations', 'departments']), $filters);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => Branch::localizedColumn(),
            'locations' => fn (Builder $query, string $direction) => $query->orderBy('locations_count', $direction),
            'departments' => fn (Builder $query, string $direction) => $query->orderBy('departments_count', $direction),
        ], default: 'code');

        return view('admin.structure.branches.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function show(Branch $branch): View
    {
        Gate::authorize('view', $branch);

        return view('admin.structure.branches.show', [
            'branch' => $branch->load(['managerPosition.department']),
            'locations' => $branch->locations()->orderByDesc('is_active')->orderBy('id')->get(),
            'departments' => $branch->departments()->with('parent')->withCount('positions')->orderBy('code')->get(),
            'defaultRadius' => setting('attendance.geofence_default_radius_meters'),
            'timezone' => $branch->timezone ?? setting('localisation.timezone'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Branch::class);

        return $this->form(new Branch(['is_active' => true]));
    }

    public function store(BranchRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $branch = $save->handle(new Branch, $request->recordData());

        return redirect()->route('admin.branches.show', $branch)->with('status', __('structure.messages.created', ['name' => $branch->name]));
    }

    public function edit(Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return $this->form($branch);
    }

    public function update(BranchRequest $request, Branch $branch, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($branch, $request->recordData());

        return redirect()->route('admin.branches.show', $branch)->with('status', __('structure.messages.updated', ['name' => $branch->name]));
    }

    public function destroy(Branch $branch, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($branch, $delete, 'admin.branches.index');
    }

    public function status(Request $request, Branch $branch, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $branch, $changeStatus);
    }

    private function form(Branch $branch): View
    {
        return $this->formPage($branch, 'branches', 'branches', [
            'cancelUrl' => $branch->exists ? route('admin.branches.show', $branch) : route('admin.branches.index'),
            'positions' => Position::query()->with('department')->activeOrKeep($branch->manager_position_id)->orderBy('code')->get(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
