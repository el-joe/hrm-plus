<?php

namespace App\Http\Resources\V1;

use App\Enums\TaskAssignee;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The employee's own onboarding with its tasks (mobile app).
 *
 * @mixin Onboarding
 */
class OnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'start_date' => $this->start_date->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'progress' => $this->progress(),
            'tasks' => $this->tasks->map(fn (OnboardingTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'assignee' => $task->assignee->value,
                'assignee_label' => $task->assignee->label(),
                'due_date' => $task->due_date?->toDateString(),
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'is_overdue' => $task->isOverdue(),
                'can_complete' => $task->assignee === TaskAssignee::Employee && ! $task->status->isFinished(),
                'completed_at' => $task->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
