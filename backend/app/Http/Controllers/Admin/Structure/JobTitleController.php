<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\JobTitleRequest;
use App\Models\JobTitle;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobTitleController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JobTitle::class);

        $filters = $this->filters($request);
        $query = $this->applyFilters(JobTitle::query()->withCount('positions'), $filters);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => JobTitle::localizedColumn(),
            'positions' => fn (Builder $query, string $direction) => $query->orderBy('positions_count', $direction),
        ], default: 'code');

        return view('admin.structure.job-titles.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', JobTitle::class);

        return $this->form(new JobTitle(['is_active' => true]));
    }

    public function store(JobTitleRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $jobTitle = $save->handle(new JobTitle, $request->recordData());

        return redirect()->route('admin.job-titles.index')->with('status', __('structure.messages.created', ['name' => $jobTitle->name]));
    }

    public function edit(JobTitle $jobTitle): View
    {
        Gate::authorize('update', $jobTitle);

        return $this->form($jobTitle);
    }

    public function update(JobTitleRequest $request, JobTitle $jobTitle, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($jobTitle, $request->recordData());

        return redirect()->route('admin.job-titles.index')->with('status', __('structure.messages.updated', ['name' => $jobTitle->name]));
    }

    public function destroy(JobTitle $jobTitle, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($jobTitle, $delete, 'admin.job-titles.index');
    }

    public function status(Request $request, JobTitle $jobTitle, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $jobTitle, $changeStatus);
    }

    private function form(JobTitle $jobTitle): View
    {
        return $this->formPage($jobTitle, 'job-titles', 'job_titles');
    }
}
