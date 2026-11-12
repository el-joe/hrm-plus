<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Enums\TrainingProviderType;
use App\Http\Requests\Admin\Training\TrainingProviderRequest;
use App\Models\TrainingProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Training providers (internal academies and outside companies).
 */
class TrainingProviderController extends TrainingCatalogueController
{
    protected function names(): array
    {
        return ['training-providers', 'providers'];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TrainingProvider::class);

        $filters = $this->filters($request);
        $records = $this->applyFilters(TrainingProvider::query()->withCount('courses'), $filters)->orderByName()->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.training.training-providers.index', ['records' => $records, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', TrainingProvider::class);

        return $this->catalogueForm(new TrainingProvider(['is_active' => true]), $this->formData());
    }

    public function store(TrainingProviderRequest $request): RedirectResponse
    {
        return $this->saved(TrainingProvider::create($request->recordData()));
    }

    public function edit(TrainingProvider $trainingProvider): View
    {
        Gate::authorize('update', $trainingProvider);

        return $this->catalogueForm($trainingProvider, $this->formData());
    }

    public function update(TrainingProviderRequest $request, TrainingProvider $trainingProvider): RedirectResponse
    {
        $trainingProvider->update($request->recordData());

        return $this->saved($trainingProvider);
    }

    public function destroy(TrainingProvider $trainingProvider): RedirectResponse
    {
        return $this->destroyRecord($trainingProvider);
    }

    public function status(Request $request, TrainingProvider $trainingProvider, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->toggle($request, $trainingProvider, $changeStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return ['types' => TrainingProviderType::cases()];
    }
}
