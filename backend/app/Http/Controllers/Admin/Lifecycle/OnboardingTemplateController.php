<?php

namespace App\Http\Controllers\Admin\Lifecycle;

use App\Enums\TaskAssignee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lifecycle\OnboardingTemplateRequest;
use App\Http\Requests\Admin\Lifecycle\TaskRequest;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Onboarding checklist templates and their tasks (onboarding.manage, on the routes).
 * A template already used keeps existing onboardings unchanged (tasks are copied).
 */
class OnboardingTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.lifecycle.templates.index', [
            'templates' => OnboardingTemplate::query()->with(['department', 'position', 'employmentType'])->withCount(['tasks', 'onboardings'])
                ->orderBy(OnboardingTemplate::localizedColumn())->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.lifecycle.templates.form', ['template' => new OnboardingTemplate(['is_active' => true]), ...$this->options(null)]);
    }

    public function store(OnboardingTemplateRequest $request): RedirectResponse
    {
        $template = OnboardingTemplate::create($request->payload());

        return redirect()->route('admin.onboarding-templates.show', $template)->with('status', __('onboarding.messages.template_saved'));
    }

    public function show(OnboardingTemplate $onboardingTemplate): View
    {
        return view('admin.lifecycle.templates.show', [
            'template' => $onboardingTemplate->load(['tasks', 'department', 'position', 'employmentType']),
            'assignees' => TaskAssignee::cases(),
        ]);
    }

    public function edit(OnboardingTemplate $onboardingTemplate): View
    {
        return view('admin.lifecycle.templates.form', ['template' => $onboardingTemplate, ...$this->options($onboardingTemplate)]);
    }

    public function update(OnboardingTemplateRequest $request, OnboardingTemplate $onboardingTemplate): RedirectResponse
    {
        $onboardingTemplate->update($request->payload());

        return redirect()->route('admin.onboarding-templates.show', $onboardingTemplate)->with('status', __('onboarding.messages.template_saved'));
    }

    public function destroy(OnboardingTemplate $onboardingTemplate): RedirectResponse
    {
        $onboardingTemplate->delete();

        return redirect()->route('admin.onboarding-templates.index')->with('status', __('onboarding.messages.template_deleted'));
    }

    public function storeTask(TaskRequest $request, OnboardingTemplate $onboardingTemplate): RedirectResponse
    {
        $onboardingTemplate->tasks()->create([
            ...$request->safe()->only(['title_ar', 'title_en', 'description', 'assignee', 'due_offset_days']),
            'sort_order' => $request->validated('sort_order') ?? ((int) $onboardingTemplate->tasks()->max('sort_order') + 10),
        ]);

        return back()->with('status', __('onboarding.messages.task_added'));
    }

    public function editTask(OnboardingTemplate $onboardingTemplate, OnboardingTemplateTask $task): View
    {
        return view('admin.lifecycle.templates.task', ['template' => $onboardingTemplate, 'task' => $task, 'assignees' => TaskAssignee::cases()]);
    }

    public function updateTask(TaskRequest $request, OnboardingTemplate $onboardingTemplate, OnboardingTemplateTask $task): RedirectResponse
    {
        $task->update($request->safe()->only(['title_ar', 'title_en', 'description', 'assignee', 'due_offset_days', 'sort_order']));

        return redirect()->route('admin.onboarding-templates.show', $onboardingTemplate)->with('status', __('onboarding.messages.task_updated'));
    }

    public function destroyTask(OnboardingTemplate $onboardingTemplate, OnboardingTemplateTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('status', __('onboarding.messages.task_removed'));
    }

    /**
     * @return array<string, mixed>
     */
    private function options(?OnboardingTemplate $template): array
    {
        return [
            'departments' => Department::query()->activeOrKeep($template?->department_id)->orderBy('code')->get(),
            'positions' => Position::query()->activeOrKeep($template?->position_id)->orderBy('code')->get(),
            'employmentTypes' => EmploymentType::query()->activeOrKeep($template?->employment_type_id)->orderBy('code')->get(),
        ];
    }
}
