<?php

namespace App\Actions\Onboarding;

use App\Enums\OnboardingStatus;
use App\Enums\TaskStatus;
use App\Models\OnboardingTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mark an onboarding task done / skipped / pending again. The onboarding
 * completes itself when no task is pending and reopens if one is reset.
 */
class UpdateOnboardingTask
{
    public function handle(OnboardingTask $task, TaskStatus $status, User $actor, ?string $note = null): OnboardingTask
    {
        $onboarding = $task->onboarding()->withoutGlobalScopes()->firstOrFail();

        if ($onboarding->status === OnboardingStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => __('onboarding.validation.onboarding_cancelled')]);
        }

        return DB::transaction(function () use ($task, $status, $actor, $note, $onboarding): OnboardingTask {
            $task->forceFill([
                'status' => $status,
                'note' => $note ?? $task->note,
                'completed_at' => $status->isFinished() ? now() : null,
                'completed_by' => $status->isFinished() ? $actor->getKey() : null,
            ])->save();

            $pending = $onboarding->tasks()->where('status', TaskStatus::Pending)->exists();

            if (! $pending && $onboarding->status === OnboardingStatus::InProgress) {
                $onboarding->forceFill(['status' => OnboardingStatus::Completed, 'completed_at' => now()])->save();
            } elseif ($pending && $onboarding->status === OnboardingStatus::Completed) {
                $onboarding->forceFill(['status' => OnboardingStatus::InProgress, 'completed_at' => null])->save();
            }

            return $task;
        });
    }
}
