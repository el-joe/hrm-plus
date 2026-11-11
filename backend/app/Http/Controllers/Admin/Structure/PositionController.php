<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\PositionRequest;
use App\Models\Department;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PositionController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Position::class);

        $filters = $this->filters($request, ['department_id', 'job_title_id', 'job_grade_id']);
        $query = $this->applyFilters(Position::query()->with(['department', 'jobTitle', 'jobGrade', 'reportsTo']), $filters, ['department_id', 'job_title_id', 'job_grade_id']);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => Position::localizedColumn(),
            'headcount' => 'headcount',
            'department' => fn (Builder $query, string $direction) => $query->orderBy(
                Department::query()->select(Department::localizedColumn())->whereColumn('departments.id', 'positions.department_id'), $direction,
            ),
        ], default: 'code');

        return view('admin.structure.positions.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'departments' => Department::query()->orderBy('code')->get(),
            'jobTitles' => JobTitle::query()->orderBy('code')->get(),
            'jobGrades' => JobGrade::query()->orderBy('level')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Position::class);

        return $this->form(new Position(['is_active' => true]));
    }

    public function store(PositionRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $position = $save->handle(new Position, $request->recordData());

        return redirect()->route('admin.positions.index')->with('status', __('structure.messages.created', ['name' => $position->name]));
    }

    public function edit(Position $position): View
    {
        Gate::authorize('update', $position);

        return $this->form($position);
    }

    public function update(PositionRequest $request, Position $position, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($position, $request->recordData());

        return redirect()->route('admin.positions.index')->with('status', __('structure.messages.updated', ['name' => $position->name]));
    }

    public function destroy(Position $position, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($position, $delete, 'admin.positions.index');
    }

    public function status(Request $request, Position $position, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $position, $changeStatus);
    }

    private function form(Position $position): View
    {
        return $this->formPage($position, 'positions', 'positions', [
            'departments' => Department::query()->activeOrKeep($position->department_id)->orderBy('code')->get(),
            'jobTitles' => JobTitle::query()->activeOrKeep($position->job_title_id)->orderBy('code')->get(),
            'jobGrades' => JobGrade::query()->activeOrKeep($position->job_grade_id)->orderBy('level')->get(),
            'managers' => Position::query()->with('department')->activeOrKeep($position->reports_to_position_id)
                ->whereKeyNot($position->getKey() ?? 0)->whereKeyNot($position->exists ? $position->descendantIds() : [])
                ->orderBy('code')->get(),
        ]);
    }
}
