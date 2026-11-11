<?php

namespace App\Actions\Onboarding;

use App\Enums\OnboardingStatus;
use App\Enums\TaskStatus;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Validation\ValidationException;

/**
 * Ad-hoc changes to a running onboarding: extra tasks, removing a task, cancelling.
 */
class ManageOnboarding
{
    /**
     * @param  array{title_ar: string, title_en: string, description?: string|null, assignee: string, due_date?: string|null}  $data
     */
    public function addTask(Onboarding $onboarding, array $data): OnboardingTask
    {
        $this->ensureOpen($onboarding);

        $task = $onboarding->tasks()->create([
            ...$data,
            'status' => TaskStatus::Pending,
            'sort_order' => (int) $onboarding->tasks()->max('sort_order') + 10,
        ]);

        if ($onboarding->status === OnboardingStatus::Completed) {
            $onboarding->forceFill(['status' => OnboardingStatus::InProgress, 'completed_at' => null])->save();
        }

        return $task;
    }

    public function removeTask(OnboardingTask $task): void
    {
        $this->ensureOpen($task->onboarding);
        $task->delete();
    }

    public function cancel(Onboarding $onboarding): void
    {
        $this->ensureOpen($onboarding);
        $onboarding->forceFill(['status' => OnboardingStatus::Cancelled])->save();
    }

    private function ensureOpen(Onboarding $onboarding): void
    {
        if ($onboarding->status === OnboardingStatus::Cancelled) {
            throw ValidationException::withMessages(['title_en' => __('onboarding.validation.onboarding_cancelled')]);
        }
    }
}
