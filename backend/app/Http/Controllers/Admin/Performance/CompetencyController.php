<?php

namespace App\Http\Controllers\Admin\Performance;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Enums\CompetencyCategory;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Performance\CompetencyRequest;
use App\Models\Competency;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The competency framework. Competencies used by a cycle or a review are deactivated, not deleted.
 */
class CompetencyController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Competency::class);

        $filters = [...$this->filters($request), ...$request->validate(['category' => ['nullable', Rule::enum(CompetencyCategory::class)]])];

        return view('admin.performance.competencies.index', [
            'records' => $this->applyFilters(Competency::query(), $filters)
                ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
                ->orderBy('category')->orderBy('code')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'categories' => CompetencyCategory::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Competency::class);

        return $this->form(new Competency(['is_active' => true, 'category' => CompetencyCategory::Core]));
    }

    public function store(CompetencyRequest $request): RedirectResponse
    {
        $competency = Competency::create($request->recordData());

        return redirect()->route('admin.competencies.index')->with('status', __('performance.competencies.saved', ['name' => $competency->name]));
    }

    public function edit(Competency $competency): View
    {
        Gate::authorize('update', $competency);

        return $this->form($competency);
    }

    public function update(CompetencyRequest $request, Competency $competency): RedirectResponse
    {
        $competency->update($request->recordData());

        return redirect()->route('admin.competencies.index')->with('status', __('performance.competencies.saved', ['name' => $competency->name]));
    }

    public function destroy(Competency $competency, DataScopeManager $scopes): RedirectResponse
    {
        Gate::authorize('delete', $competency);

        $usages = array_filter($scopes->withoutScoping(fn (): array => $competency->usageCounts()));

        if ($usages !== []) {
            $parts = array_map(fn (string $key, int $count): string => trans_choice('performance.usage.'.$key, $count, ['count' => $count]), array_keys($usages), $usages);

            throw ValidationException::withMessages(['record' => __('performance.messages.in_use', ['name' => $competency->name, 'usages' => implode(__('structure.messages.list_separator'), $parts)])]);
        }

        $competency->delete();

        return redirect()->route('admin.competencies.index')->with('status', __('structure.messages.deleted', ['name' => $competency->name]));
    }

    public function status(Request $request, Competency $competency, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $competency, $changeStatus);
    }

    private function form(Competency $competency): View
    {
        return view('admin.performance.competencies.form', ['record' => $competency, 'categories' => CompetencyCategory::cases()]);
    }
}
