<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\CostCenterRequest;
use App\Models\CostCenter;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CostCenterController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CostCenter::class);

        $filters = $this->filters($request, ['parent_id']);
        $query = $this->applyFilters(CostCenter::query()->with('parent')->withCount('departments'), $filters, ['parent_id']);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => CostCenter::localizedColumn(),
            'departments' => fn (Builder $query, string $direction) => $query->orderBy('departments_count', $direction),
        ], default: 'code');

        return view('admin.structure.cost-centers.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'parents' => CostCenter::query()->has('children')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', CostCenter::class);

        return $this->form(new CostCenter(['is_active' => true]));
    }

    public function store(CostCenterRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $costCenter = $save->handle(new CostCenter, $request->recordData());

        return redirect()->route('admin.cost-centers.index')->with('status', __('structure.messages.created', ['name' => $costCenter->name]));
    }

    public function edit(CostCenter $costCenter): View
    {
        Gate::authorize('update', $costCenter);

        return $this->form($costCenter);
    }

    public function update(CostCenterRequest $request, CostCenter $costCenter, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($costCenter, $request->recordData());

        return redirect()->route('admin.cost-centers.index')->with('status', __('structure.messages.updated', ['name' => $costCenter->name]));
    }

    public function destroy(CostCenter $costCenter, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($costCenter, $delete, 'admin.cost-centers.index');
    }

    public function status(Request $request, CostCenter $costCenter, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $costCenter, $changeStatus);
    }

    private function form(CostCenter $costCenter): View
    {
        return $this->formPage($costCenter, 'cost-centers', 'cost_centers', [
            'parents' => CostCenter::query()->activeOrKeep($costCenter->parent_id)
                ->whereKeyNot($costCenter->getKey() ?? 0)->whereKeyNot($costCenter->exists ? $costCenter->descendantIds() : [])
                ->orderBy('code')->get(),
        ]);
    }
}
