<?php

namespace App\Http\Controllers\Admin\Performance;

use App\Actions\Performance\ManagePerformanceCycle;
use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use App\Enums\PerformanceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Performance\PerformanceCycleRequest;
use App\Models\Branch;
use App\Models\Competency;
use App\Models\Department;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Review cycles: list, configure (draft), launch (creates the reviews), close, and the
 * cycle page with progress by status and rating distribution. Rules live in
 * App\Actions\Performance\ManagePerformanceCycle.
 */
class PerformanceCycleController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PerformanceCycle::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PerformanceCycleStatus::class)],
            'type' => ['nullable', Rule::enum(PerformanceCycleType::class)],
        ]);

        return view('admin.performance.cycles.index', [
            'cycles' => PerformanceCycle::query()->with('ratingScale')
                ->withCount(['reviews', 'reviews as finalised_count' => fn ($query) => $query->finalised()])
                ->when($filters['search'] ?? null, fn ($query, string $search) => $query->search($search))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
                ->orderByDesc('period_start')->orderByDesc('id')
                ->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'statuses' => PerformanceCycleStatus::cases(),
            'types' => PerformanceCycleType::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PerformanceCycle::class);

        return $this->form(new PerformanceCycle([
            'type' => PerformanceCycleType::Annual, 'goals_weight' => '70.00', 'competencies_weight' => '30.00', 'self_assessment_enabled' => true,
            'rating_scale_id' => RatingScale::query()->active()->orderBy('id')->value('id'),
        ]));
    }

    public function store(PerformanceCycleRequest $request, ManagePerformanceCycle $cycles): RedirectResponse
    {
        $cycle = $cycles->save(null, $request->cycleData(), $request->user());

        return redirect()->route('admin.performance-cycles.show', $cycle)->with('status', __('performance.cycles.saved', ['name' => $cycle->name]));
    }

    public function show(PerformanceCycle $performanceCycle, ManagePerformanceCycle $cycles): View
    {
        Gate::authorize('view', $performanceCycle);

        $performanceCycle->load(['ratingScale.levels', 'competencies.competency', 'branch', 'department']);
        $reviews = PerformanceReview::query()->where('performance_cycle_id', $performanceCycle->id);

        return view('admin.performance.cycles.show', [
            'cycle' => $performanceCycle,
            'statusCounts' => (clone $reviews)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'ratingCounts' => (clone $reviews)->finalised()->toBase()->selectRaw('final_rating_level_id, count(*) as total')->groupBy('final_rating_level_id')->pluck('total', 'final_rating_level_id'),
            'reviewTotal' => (clone $reviews)->where('status', '!=', PerformanceReviewStatus::Cancelled->value)->count(),
            'participants' => $performanceCycle->isDraft() ? $cycles->participants($performanceCycle)->count() : null,
            'reviewStatuses' => PerformanceReviewStatus::cases(),
        ]);
    }

    public function edit(PerformanceCycle $performanceCycle): View
    {
        Gate::authorize('update', $performanceCycle);
        abort_if($performanceCycle->isClosed(), 404);

        return $this->form($performanceCycle->load('competencies'));
    }

    public function update(PerformanceCycleRequest $request, PerformanceCycle $performanceCycle, ManagePerformanceCycle $cycles): RedirectResponse
    {
        $cycles->save($performanceCycle, $request->cycleData(), $request->user());

        return redirect()->route('admin.performance-cycles.show', $performanceCycle)->with('status', __('performance.cycles.saved', ['name' => $performanceCycle->name]));
    }

    public function destroy(PerformanceCycle $performanceCycle, ManagePerformanceCycle $cycles): RedirectResponse
    {
        Gate::authorize('delete', $performanceCycle);
        $cycles->delete($performanceCycle);

        return redirect()->route('admin.performance-cycles.index')->with('status', __('performance.cycles.deleted', ['name' => $performanceCycle->name]));
    }

    public function launch(Request $request, PerformanceCycle $performanceCycle, ManagePerformanceCycle $cycles): RedirectResponse
    {
        Gate::authorize('update', $performanceCycle);
        $count = $cycles->launch($performanceCycle, $request->user());

        return back()->with('status', __('performance.cycles.launched', ['count' => $count]));
    }

    public function close(Request $request, PerformanceCycle $performanceCycle, ManagePerformanceCycle $cycles): RedirectResponse
    {
        Gate::authorize('update', $performanceCycle);
        $cycles->close($performanceCycle, $request->user());

        return back()->with('status', __('performance.cycles.closed', ['name' => $performanceCycle->name]));
    }

    private function form(PerformanceCycle $cycle): View
    {
        return view('admin.performance.cycles.form', [
            'cycle' => $cycle,
            'types' => PerformanceCycleType::cases(),
            'scales' => RatingScale::query()->activeOrKeep($cycle->rating_scale_id)->orderBy('code')->get(),
            'branches' => Branch::query()->activeOrKeep($cycle->branch_id)->orderByName()->get(),
            'departments' => Department::query()->activeOrKeep($cycle->department_id)->orderByName()->get(),
            'competencyOptions' => Competency::query()->active()->orderBy('category')->orderBy('code')->get(),
            'cycleCompetencies' => $cycle->exists ? $cycle->competencies->map->only(['competency_id', 'weight'])->values()->all() : [],
        ]);
    }
}
