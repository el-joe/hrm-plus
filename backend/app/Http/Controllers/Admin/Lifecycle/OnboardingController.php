<?php

namespace App\Http\Controllers\Admin\Lifecycle;

use App\Actions\Onboarding\ManageOnboarding;
use App\Actions\Onboarding\StartOnboarding;
use App\Actions\Onboarding\UpdateOnboardingTask;
use App\Enums\OnboardingStatus;
use App\Enums\TaskAssignee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lifecycle\StartOnboardingRequest;
use App\Http\Requests\Admin\Lifecycle\TaskRequest;
use App\Http\Requests\Admin\Lifecycle\TaskStatusRequest;
use App\Models\Employee;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Onboardings: progress list, start one manually, the checklist page.
 */
class OnboardingController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Onboarding::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OnboardingStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.lifecycle.onboarding.index', [
            'onboardings' => Onboarding::query()
                ->with(['employee.department', 'employee.jobTitle', 'template', 'tasks'])
                ->where('status', $filters['status'] ?? OnboardingStatus::InProgress->value)
                ->when($filters['search'] ?? null, fn ($query, string $term) => $query->whereHas('employee', fn ($query) => $query->search($term)))
                ->latest('start_date')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Onboarding::class);

        return view('admin.lifecycle.onboarding.create', [
            'employees' => Employee::query()->active()
                ->whereDoesntHave('onboardings', fn ($query) => $query->where('status', OnboardingStatus::InProgress))
                ->orderByDesc('hire_date')->limit(500)->get(),
            'templates' => OnboardingTemplate::query()->active()->orderBy(OnboardingTemplate::localizedColumn())->get(),
            'selectedEmployee' => $request->integer('employee') ?: null,
        ]);
    }

    public function store(StartOnboardingRequest $request, StartOnboarding $start): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $template = $request->filled('onboarding_template_id') ? OnboardingTemplate::query()->findOrFail($request->integer('onboarding_template_id')) : null;

        $onboarding = $start->handle($employee, $template, null, Carbon::parse($request->validated('start_date')), $request->user());

        return redirect()->route('admin.onboarding.show', $onboarding)->with('status', __('onboarding.messages.started', ['name' => $employee->name]));
    }

    public function show(Request $request, Onboarding $onboarding): View
    {
        Gate::authorize('view', $onboarding);

        $onboarding->load(['employee.department', 'employee.jobTitle', 'employee.manager', 'template', 'application.vacancy', 'tasks.completedBy']);

        return view('admin.lifecycle.onboarding.show', [
            'onboarding' => $onboarding,
            'canUpdate' => $request->user()->can('update', $onboarding),
            'assignees' => TaskAssignee::cases(),
        ]);
    }

    public function updateTask(TaskStatusRequest $request, Onboarding $onboarding, OnboardingTask $task, UpdateOnboardingTask $update): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        $update->handle($task, $request->status(), $request->user(), $request->validated('note'));

        return back()->with('status', __('onboarding.messages.task_updated'));
    }

    public function storeTask(TaskRequest $request, Onboarding $onboarding, ManageOnboarding $manage): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        $manage->addTask($onboarding, $request->safe()->only(['title_ar', 'title_en', 'description', 'assignee', 'due_date']));

        return back()->with('status', __('onboarding.messages.task_added'));
    }

    public function destroyTask(Onboarding $onboarding, OnboardingTask $task, ManageOnboarding $manage): RedirectResponse
    {
        Gate::authorize('update', $onboarding);
        $manage->removeTask($task);

        return back()->with('status', __('onboarding.messages.task_removed'));
    }

    public function cancel(Onboarding $onboarding, ManageOnboarding $manage): RedirectResponse
    {
        Gate::authorize('delete', $onboarding);
        $manage->cancel($onboarding);

        return back()->with('status', __('onboarding.messages.cancelled'));
    }
}
