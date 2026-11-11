<?php

namespace App\Http\Controllers\Admin\Lifecycle;

use App\Actions\Offboarding\ManageOffboardingCase;
use App\Actions\Offboarding\OpenOffboardingCase;
use App\Enums\HandoverCategory;
use App\Enums\OffboardingStatus;
use App\Enums\TaskAssignee;
use App\Enums\TerminationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lifecycle\ExitInterviewRequest;
use App\Http\Requests\Admin\Lifecycle\HandoverItemRequest;
use App\Http\Requests\Admin\Lifecycle\OffboardingCaseRequest;
use App\Http\Requests\Admin\Lifecycle\TaskStatusRequest;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Offboarding cases: list, open, the case page (details, handover
 * checklist, exit interview), completion (terminates the employee) and cancel.
 */
class OffboardingController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', OffboardingCase::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OffboardingStatus::class)],
            'type' => ['nullable', Rule::enum(TerminationType::class)],
        ]);

        return view('admin.lifecycle.offboarding.index', [
            'cases' => OffboardingCase::query()
                ->with(['employee.department', 'items'])
                ->where('status', $filters['status'] ?? OffboardingStatus::Open->value)
                ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
                ->orderBy('last_working_date')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', OffboardingCase::class);

        return view('admin.lifecycle.offboarding.create', [
            'case' => new OffboardingCase(['last_working_date' => today()->addDays(30), 'notice_date' => today(), 'eligible_for_rehire' => true]),
            'employees' => Employee::query()->employed()
                ->whereDoesntHave('offboardingCases', fn ($query) => $query->where('status', OffboardingStatus::Open))
                ->orderBy('employee_number')->get(),
            'selectedEmployee' => $request->integer('employee') ?: null,
            'types' => TerminationType::cases(),
        ]);
    }

    public function store(OffboardingCaseRequest $request, OpenOffboardingCase $open): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $case = $open->handle($employee, $request->payload(), $request->user());

        return redirect()->route('admin.offboarding.show', $case)->with('status', __('onboarding.messages.case_opened', ['name' => $employee->name]));
    }

    public function show(Request $request, OffboardingCase $offboarding): View
    {
        Gate::authorize('view', $offboarding);

        $offboarding->load(['employee.department', 'employee.jobTitle', 'items.completedBy', 'exitInterviewer', 'completedBy', 'contract']);
        $user = $request->user();

        return view('admin.lifecycle.offboarding.show', [
            'case' => $offboarding,
            'linkedAccounts' => User::query()->where('employee_id', $offboarding->employee_id)->get(),
            'canUpdate' => $user->can('update', $offboarding),
            'canComplete' => $user->can('complete', $offboarding),
            'canCancel' => $user->can('cancel', $offboarding),
            'types' => TerminationType::cases(),
            'categories' => HandoverCategory::cases(),
            'assignees' => TaskAssignee::cases(),
            'questions' => config('lifecycle.exit_interview'),
        ]);
    }

    public function update(OffboardingCaseRequest $request, OffboardingCase $offboarding, ManageOffboardingCase $manage): RedirectResponse
    {
        $manage->update($offboarding, $request->payload());

        return back()->with('status', __('onboarding.messages.case_updated'));
    }

    public function updateItem(TaskStatusRequest $request, OffboardingCase $offboarding, OffboardingItem $item, ManageOffboardingCase $manage): RedirectResponse
    {
        Gate::authorize('update', $offboarding);
        $manage->updateItem($item, $request->status(), $request->user(), $request->validated('note'));

        return back()->with('status', __('onboarding.messages.item_updated'));
    }

    public function storeItem(HandoverItemRequest $request, OffboardingCase $offboarding, ManageOffboardingCase $manage): RedirectResponse
    {
        $manage->addItem($offboarding, $request->validated());

        return back()->with('status', __('onboarding.messages.item_added'));
    }

    public function exitInterview(ExitInterviewRequest $request, OffboardingCase $offboarding, ManageOffboardingCase $manage): RedirectResponse
    {
        $manage->recordExitInterview($offboarding, $request->answers(), $request->validated('notes'), $request->user());

        return back()->with('status', __('onboarding.messages.exit_saved'));
    }

    public function complete(Request $request, OffboardingCase $offboarding, ManageOffboardingCase $manage): RedirectResponse
    {
        Gate::authorize('complete', $offboarding);
        $manage->complete($offboarding, $request->user());

        return redirect()->route('admin.offboarding.show', $offboarding)->with('status', __(
            $offboarding->last_working_date->isFuture() ? 'onboarding.messages.case_completed_scheduled' : 'onboarding.messages.case_completed',
            ['date' => $offboarding->last_working_date->toDateString()],
        ));
    }

    public function cancel(Request $request, OffboardingCase $offboarding, ManageOffboardingCase $manage): RedirectResponse
    {
        Gate::authorize('cancel', $offboarding);
        $manage->cancel($offboarding, $request->user());

        return back()->with('status', __('onboarding.messages.case_cancelled'));
    }
}
