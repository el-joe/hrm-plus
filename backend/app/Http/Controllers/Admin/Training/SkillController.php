<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Http\Requests\Admin\Training\SkillRequest;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Skills of the skills matrix.
 */
class SkillController extends TrainingCatalogueController
{
    protected function names(): array
    {
        return ['skills', 'skills'];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Skill::class);

        $filters = $this->filters($request);
        $records = $this->applyFilters(Skill::query()->withCount(['employeeSkills' => fn ($query) => $query->withoutGlobalScopes()]), $filters)->orderByName()->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.training.skills.index', ['records' => $records, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', Skill::class);

        return $this->catalogueForm(new Skill(['is_active' => true]), $this->formData());
    }

    public function store(SkillRequest $request): RedirectResponse
    {
        return $this->saved(Skill::create($request->recordData()));
    }

    public function edit(Skill $skill): View
    {
        Gate::authorize('update', $skill);

        return $this->catalogueForm($skill, $this->formData());
    }

    public function update(SkillRequest $request, Skill $skill): RedirectResponse
    {
        $skill->update($request->recordData());

        return $this->saved($skill);
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        return $this->destroyRecord($skill);
    }

    public function status(Request $request, Skill $skill, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->toggle($request, $skill, $changeStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return ['categories' => Skill::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')];
    }
}
