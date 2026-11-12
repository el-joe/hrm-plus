<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Http\Requests\Admin\Training\TrainingProgrammeRequest;
use App\Models\TrainingProgramme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Training programmes: named groups of courses.
 */
class TrainingProgrammeController extends TrainingCatalogueController
{
    protected function names(): array
    {
        return ['training-programmes', 'programmes'];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TrainingProgramme::class);

        $filters = $this->filters($request);
        $records = $this->applyFilters(TrainingProgramme::query()->withCount('courses'), $filters)->orderByName()->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.training.training-programmes.index', ['records' => $records, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', TrainingProgramme::class);

        return $this->catalogueForm(new TrainingProgramme(['is_active' => true]), $this->formData());
    }

    public function store(TrainingProgrammeRequest $request): RedirectResponse
    {
        return $this->saved(TrainingProgramme::create($request->recordData()));
    }

    public function edit(TrainingProgramme $trainingProgramme): View
    {
        Gate::authorize('update', $trainingProgramme);

        return $this->catalogueForm($trainingProgramme, $this->formData());
    }

    public function update(TrainingProgrammeRequest $request, TrainingProgramme $trainingProgramme): RedirectResponse
    {
        $trainingProgramme->update($request->recordData());

        return $this->saved($trainingProgramme);
    }

    public function destroy(TrainingProgramme $trainingProgramme): RedirectResponse
    {
        return $this->destroyRecord($trainingProgramme);
    }

    public function status(Request $request, TrainingProgramme $trainingProgramme, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->toggle($request, $trainingProgramme, $changeStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [];
    }
}
