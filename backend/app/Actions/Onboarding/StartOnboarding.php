<?php

namespace App\Actions\Onboarding;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\TaskAssignee;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\Onboarding;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Notifications\Lifecycle\OnboardingStarted;
use App\Services\Onboarding\TemplateMatcher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Start an employee's onboarding: the template's tasks are copied (so later
 * template edits never change a running onboarding), each due on
 * start date + its offset. The employee's own account is notified when it
 * has tasks of its own.
 */
class StartOnboarding
{
    public function __construct(private readonly TemplateMatcher $matcher) {}

    public function handle(
        Employee $employee,
        ?OnboardingTemplate $template = null,
        ?JobApplication $application = null,
        ?CarbonInterface $startDate = null,
        ?User $actor = null,
    ): Onboarding {
        if ($employee->onboardings()->withoutGlobalScopes()->where('status', OnboardingStatus::InProgress)->exists()) {
            throw ValidationException::withMessages(['employee_id' => __('onboarding.validation.already_onboarding')]);
        }

        $template ??= $this->matcher->for($employee);
        $startDate ??= $employee->hire_date ?? today();

        $onboarding = DB::transaction(function () use ($employee, $template, $application, $startDate, $actor): Onboarding {
            $onboarding = Onboarding::create([
                'employee_id' => $employee->getKey(),
                'onboarding_template_id' => $template?->getKey(),
                'job_application_id' => $application?->getKey(),
                'start_date' => $startDate->toDateString(),
                'status' => OnboardingStatus::InProgress,
                'created_by' => $actor?->getKey(),
            ]);

            foreach ($template?->tasks ?? [] as $task) {
                $onboarding->tasks()->create([
                    'title_ar' => $task->title_ar,
                    'title_en' => $task->title_en,
                    'description' => $task->description,
                    'assignee' => $task->assignee,
                    'action' => $task->action,
                    'due_date' => $startDate->copy()->addDays($task->due_offset_days)->toDateString(),
                    'sort_order' => $task->sort_order,
                ]);
            }

            return $onboarding;
        });

        $hasOwnTasks = $onboarding->tasks()->where('assignee', TaskAssignee::Employee)->exists();
        $account = User::query()->where('employee_id', $employee->getKey())->where('account_type', AccountType::Employee)
            ->where('status', UserStatus::Active)->first();

        if ($hasOwnTasks && $account !== null) {
            $account->notify(new OnboardingStarted($onboarding));
        }

        return $onboarding;
    }
}
