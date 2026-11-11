<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Onboarding\UpdateOnboardingTask;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Services\Employees\PortalProfile;
use App\Services\Onboarding\PortalOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "My onboarding" in the portal: the checklist, and completing the tasks
 * assigned to the employee. Always the signed-in user's own record.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalOnboarding $onboarding) {}

    public function index(Request $request): View
    {
        $employee = $this->profile->employeeFor($request->user());

        return view('employee.onboarding', [
            'employee' => $employee,
            'onboarding' => $employee ? $this->onboarding->current($employee) : null,
        ]);
    }

    public function update(Request $request, int $task, UpdateOnboardingTask $update): RedirectResponse
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);
        $record = $this->onboarding->ownTask($employee, $task);
        abort_if($record === null, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in([TaskStatus::Done->value, TaskStatus::Pending->value])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $update->handle($record, TaskStatus::from($data['status']), $request->user(), $data['note'] ?? null);

        return back()->with('status', __('onboarding.messages.task_updated'));
    }
}
