<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\EmploymentTypeRequest;
use App\Models\EmploymentType;
use App\Support\IndexSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmploymentTypeController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmploymentType::class);

        $filters = $this->filters($request);
        $query = $this->applyFilters(EmploymentType::query(), $filters);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => EmploymentType::localizedColumn(),
            'fixed_term' => 'is_fixed_term',
        ], default: 'code');

        return view('admin.structure.employment-types.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', EmploymentType::class);

        return $this->form(new EmploymentType(['is_active' => true]));
    }

    public function store(EmploymentTypeRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $employmentType = $save->handle(new EmploymentType, $request->recordData());

        return redirect()->route('admin.employment-types.index')->with('status', __('structure.messages.created', ['name' => $employmentType->name]));
    }

    public function edit(EmploymentType $employmentType): View
    {
        Gate::authorize('update', $employmentType);

        return $this->form($employmentType);
    }

    public function update(EmploymentTypeRequest $request, EmploymentType $employmentType, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($employmentType, $request->recordData());

        return redirect()->route('admin.employment-types.index')->with('status', __('structure.messages.updated', ['name' => $employmentType->name]));
    }

    public function destroy(EmploymentType $employmentType, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($employmentType, $delete, 'admin.employment-types.index');
    }

    public function status(Request $request, EmploymentType $employmentType, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $employmentType, $changeStatus);
    }

    private function form(EmploymentType $employmentType): View
    {
        return $this->formPage($employmentType, 'employment-types', 'employment_types');
    }
}
