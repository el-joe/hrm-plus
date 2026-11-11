<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Onboarding\UpdateOnboardingTask;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OnboardingResource;
use App\Models\Employee;
use App\Services\Employees\PortalProfile;
use App\Services\Onboarding\PortalOnboarding;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/me/onboarding                       the employee's onboarding (data: null when none)
 * POST /api/v1/me/onboarding/tasks/{task}/complete  complete one of their own tasks
 *
 * Resolved from the token owner; another employee's task answers 404.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalOnboarding $onboarding) {}

    public function show(Request $request): JsonResponse
    {
        $onboarding = $this->onboarding->current($this->employee($request));

        return ApiResponse::success($onboarding ? new OnboardingResource($onboarding) : null);
    }

    public function complete(Request $request, int $task, UpdateOnboardingTask $update): JsonResponse
    {
        $employee = $this->employee($request);
        $record = $this->onboarding->ownTask($employee, $task);
        abort_if($record === null, 404);

        $update->handle($record, TaskStatus::Done, $request->user());

        return ApiResponse::success(new OnboardingResource($this->onboarding->current($employee)), __('onboarding.messages.task_updated'));
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
