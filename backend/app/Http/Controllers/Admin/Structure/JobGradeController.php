<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\JobGradeRequest;
use App\Models\JobGrade;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobGradeController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JobGrade::class);

        $filters = $this->filters($request);
        $query = $this->applyFilters(JobGrade::query()->withCount('positions'), $filters);
        $sort = IndexSorting::apply($query, $request, [
            'level' => 'level',
            'code' => 'code',
            'name' => JobGrade::localizedColumn(),
            'positions' => fn (Builder $query, string $direction) => $query->orderBy('positions_count', $direction),
        ], default: 'level');

        return view('admin.structure.job-grades.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'currency' => setting('localisation.currency_code'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', JobGrade::class);

        return $this->form(new JobGrade(['is_active' => true]));
    }

    public function store(JobGradeRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $jobGrade = $save->handle(new JobGrade, $request->recordData());

        return redirect()->route('admin.job-grades.index')->with('status', __('structure.messages.created', ['name' => $jobGrade->name]));
    }

    public function edit(JobGrade $jobGrade): View
    {
        Gate::authorize('update', $jobGrade);

        return $this->form($jobGrade);
    }

    public function update(JobGradeRequest $request, JobGrade $jobGrade, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($jobGrade, $request->recordData());

        return redirect()->route('admin.job-grades.index')->with('status', __('structure.messages.updated', ['name' => $jobGrade->name]));
    }

    public function destroy(JobGrade $jobGrade, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($jobGrade, $delete, 'admin.job-grades.index');
    }

    public function status(Request $request, JobGrade $jobGrade, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $jobGrade, $changeStatus);
    }

    private function form(JobGrade $jobGrade): View
    {
        return $this->formPage($jobGrade, 'job-grades', 'job_grades', ['currency' => setting('localisation.currency_code')]);
    }
}
