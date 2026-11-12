<?php

namespace App\Http\Resources\V1;

use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalUpdate;
use App\Services\Performance\PortalPerformance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own goal / KPI (mobile app). Numbers are DECIMAL strings.
 * "updates" (progress history) is included when loaded (detail / progress response).
 *
 * @mixin PerformanceGoal
 */
class PerformanceGoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'description' => $this->description,
            'unit' => $this->unit,
            'target_value' => $this->target_value === null ? null : (string) $this->target_value,
            'current_value' => $this->current_value === null ? null : (string) $this->current_value,
            'weight' => (string) $this->weight,
            'progress' => (string) $this->progress,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'overdue' => $this->isOverdue(),
            'cycle' => $this->cycle ? ['id' => $this->cycle->id, 'name' => $this->cycle->name] : null,
            'can_update_progress' => app(PortalPerformance::class)->canEditGoal($this->resource),
            'updates' => $this->whenLoaded('updates', fn () => $this->updates->map(fn (PerformanceGoalUpdate $update): array => [
                'progress' => (string) $update->progress,
                'current_value' => $update->current_value === null ? null : (string) $update->current_value,
                'note' => $update->note,
                'at' => $update->created_at?->toIso8601ZuluString(),
            ])->all()),
        ];
    }
}
