<?php

namespace App\Http\Controllers\Admin\Performance;

use App\Actions\Performance\ManagePerformanceGoal;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\PerformanceCycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Performance\PerformanceGoalRequest;
use App\Http\Requests\Performance\GoalProgressRequest;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceVisibility;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Goals and KPIs in the admin area (HR: in scope; managers: their reporting line):
 * list, set goals for an employee, edit, record progress, complete / cancel.
 */
class PerformanceGoalController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(private readonly PerformanceVisibility $visibility) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PerformanceGoal::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(GoalStatus::class)],
            'type' => ['nullable', Rule::enum(GoalType::class)],
            'cycle' => ['nullable', 'integer'],
            'employee' => ['nullable', 'integer'],
        ]);

        $query = $this->visibility->goals(PerformanceGoal::query(), $request->user())->with(['employee', 'cycle'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")->orWhereHas('employee', fn (Builder $employee) => $employee->search($search))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['cycle'] ?? null, fn (Builder $query, int|string $cycle) => $query->where('performance_cycle_id', $cycle))
            ->when($filters['employee'] ?? null, fn (Builder $query, int|string $employee) => $query->where('employee_id', $employee));

        $sort = IndexSorting::apply($query, $request, ['title' => 'title', 'due' => 'due_date', 'progress' => 'progress', 'weight' => 'weight', 'updated' => 'updated_at'], default: 'updated', defaultDirection: 'desc');

        return view('admin.performance.goals.index', [
            'goals' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'statuses' => GoalStatus::cases(),
            'types' => GoalType::cases(),
            'cycles' => PerformanceCycle::query()->orderByDesc('period_start')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PerformanceGoal::class);

        $employee = $request->integer('employee') ? Employee::query()->find($request->integer('employee')) : null;
        $cycle = $request->integer('cycle') ? $request->integer('cycle') : PerformanceCycle::query()->where('status', PerformanceCycleStatus::Active)->orderByDesc('period_start')->value('id');

        return $this->form(new PerformanceGoal([
            'employee_id' => $employee?->id, 'performance_cycle_id' => $cycle, 'type' => GoalType::Goal, 'weight' => '0',
        ]), $request);
    }

    public function store(PerformanceGoalRequest $request, ManagePerformanceGoal $goals): RedirectResponse
    {
        $goal = $goals->create($request->employee(), $request->validated(), $request->user());

        return redirect()->to($request->input('return') === 'review' && ($review = $this->reviewFor($goal)) ? route('admin.performance-reviews.show', $review) : route('admin.performance-goals.edit', $goal))
            ->with('status', __('performance.goals.saved', ['title' => $goal->title]));
    }

    public function edit(Request $request, PerformanceGoal $performanceGoal): View
    {
        Gate::authorize('view', $performanceGoal);

        return $this->form($performanceGoal->load(['employee', 'cycle', 'updates.creator']), $request);
    }

    public function update(PerformanceGoalRequest $request, PerformanceGoal $performanceGoal, ManagePerformanceGoal $goals): RedirectResponse
    {
        $goals->update($performanceGoal, $request->validated(), $request->user());

        return redirect()->route('admin.performance-goals.edit', $performanceGoal)->with('status', __('performance.goals.saved', ['title' => $performanceGoal->title]));
    }

    public function progress(GoalProgressRequest $request, PerformanceGoal $performanceGoal, ManagePerformanceGoal $goals): RedirectResponse
    {
        Gate::authorize('update', $performanceGoal);

        $goals->recordProgress($performanceGoal, $request->validated('progress'), $request->validated('current_value'), $request->validated('note'), $request->user());

        return back()->with('status', __('performance.goals.progress_saved'));
    }

    public function status(Request $request, PerformanceGoal $performanceGoal, ManagePerformanceGoal $goals): RedirectResponse
    {
        Gate::authorize('update', $performanceGoal);

        $status = GoalStatus::from($request->validate(['status' => ['required', Rule::enum(GoalStatus::class)]])['status']);
        $goals->changeStatus($performanceGoal, $status, $request->user());

        return back()->with('status', __('performance.goals.status_saved', ['status' => $status->label()]));
    }

    private function reviewFor(PerformanceGoal $goal): ?int
    {
        return $goal->performance_cycle_id === null ? null : PerformanceReview::query()
            ->where('employee_id', $goal->employee_id)->where('performance_cycle_id', $goal->performance_cycle_id)->value('id');
    }

    private function form(PerformanceGoal $goal, Request $request): View
    {
        return view('admin.performance.goals.form', [
            'goal' => $goal,
            'employees' => $goal->exists ? collect() : $this->visibility->employees(Employee::query()->active(), $request->user())->orderBy('employee_number')->get(),
            'cycles' => PerformanceCycle::query()->whereIn('status', [PerformanceCycleStatus::Draft->value, PerformanceCycleStatus::Active->value])
                ->when($goal->performance_cycle_id, fn (Builder $query, int $id) => $query->orWhere('id', $id))->orderByDesc('period_start')->get(),
            'types' => GoalType::cases(),
            'canUpdate' => $goal->exists ? $request->user()->can('update', $goal) : true,
            'returnTo' => $request->query('return'),
        ]);
    }
}
