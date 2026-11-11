<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Actions\Structure\SaveStructureRecord;
use App\Http\Requests\Admin\Structure\TeamRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeamController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Team::class);

        $filters = $this->filters($request, ['department_id']);
        $query = $this->applyFilters(Team::query()->with(['department', 'leader'])->withCount(['employees' => fn (Builder $query) => $query->employed()]), $filters, ['department_id']);
        $sort = IndexSorting::apply($query, $request, [
            'code' => 'code',
            'name' => Team::localizedColumn(),
            'department' => fn (Builder $query, string $direction) => $query->orderBy(
                Department::query()->select(Department::localizedColumn())->whereColumn('departments.id', 'teams.department_id'), $direction,
            ),
        ], default: 'code');

        return view('admin.structure.teams.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Team::class);

        return $this->form(new Team(['is_active' => true]));
    }

    public function store(TeamRequest $request, SaveStructureRecord $save): RedirectResponse
    {
        $team = $save->handle(new Team, $request->recordData());

        return redirect()->route('admin.teams.index')->with('status', __('structure.messages.created', ['name' => $team->name]));
    }

    public function edit(Team $team): View
    {
        Gate::authorize('update', $team);

        return $this->form($team);
    }

    public function update(TeamRequest $request, Team $team, SaveStructureRecord $save): RedirectResponse
    {
        $save->handle($team, $request->recordData());

        return redirect()->route('admin.teams.index')->with('status', __('structure.messages.updated', ['name' => $team->name]));
    }

    public function destroy(Team $team, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($team, $delete, 'admin.teams.index');
    }

    public function status(Request $request, Team $team, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $team, $changeStatus);
    }

    private function form(Team $team): View
    {
        return $this->formPage($team, 'teams', 'teams', [
            'departments' => Department::query()->activeOrKeep($team->department_id)->orderBy('code')->get(),
            'leaders' => Employee::query()->activeOrKeep($team->leader_employee_id)->orderBy('employee_number')->get(),
        ]);
    }
}
