<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\BranchLocationRequest;
use App\Models\Branch;
use App\Models\BranchLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Attendance locations of a branch (GPS point + geofence radius). Managed
 * from the branch page with branches.update.
 */
class BranchLocationController extends StructureController
{
    public function create(Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return $this->form($branch, new BranchLocation([
            'is_active' => true,
            'radius_meters' => setting('attendance.geofence_default_radius_meters'),
        ]));
    }

    public function store(BranchLocationRequest $request, Branch $branch, SaveStructureRecord $save): RedirectResponse
    {
        $location = $save->handle($branch->locations()->make(), $request->recordData());

        return redirect()->route('admin.branches.show', $branch)->with('status', __('structure.messages.created', ['name' => $location->name]));
    }

    public function edit(Branch $branch, BranchLocation $location): View
    {
        Gate::authorize('update', $branch);

        return $this->form($branch, $location);
    }

    public function update(BranchLocationRequest $request, Branch $branch, BranchLocation $location, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($location, $request->recordData());

        return redirect()->route('admin.branches.show', $branch)->with('status', __('structure.messages.updated', ['name' => $location->name]));
    }

    public function destroy(Branch $branch, BranchLocation $location, DeleteStructureRecord $delete): RedirectResponse
    {
        Gate::authorize('update', $branch);

        $delete->handle($location);

        return redirect()->route('admin.branches.show', $branch)->with('status', __('structure.messages.deleted', ['name' => $location->name]));
    }

    private function form(Branch $branch, BranchLocation $location): View
    {
        $branchUrl = route('admin.branches.show', $branch);

        return $this->formPage($location, 'branches.locations', 'locations', [
            'formView' => 'admin.structure.branches._location-form',
            'branch' => $branch,
            'cancelUrl' => $branchUrl,
            'breadcrumbs' => [
                ['label' => __('structure.branches.title'), 'url' => route('admin.branches.index')],
                ['label' => $branch->name, 'url' => $branchUrl],
            ],
        ], [$branch]);
    }
}
