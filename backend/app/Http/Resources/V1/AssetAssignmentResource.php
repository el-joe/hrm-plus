<?php

namespace App\Http\Resources\V1;

use App\Models\AssetAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own asset custody period (mobile app). The purchase cost is
 * never exposed to the employee.
 *
 * @mixin AssetAssignment
 */
class AssetAssignmentResource extends JsonResource
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
            'asset' => [
                'id' => $this->asset->id,
                'tag' => $this->asset->asset_tag,
                'name' => $this->asset->name,
                'brand' => $this->asset->brand,
                'model' => $this->asset->model,
                'serial_number' => $this->asset->serial_number,
                'category' => $this->asset->category?->name,
                'category_code' => $this->asset->category?->code,
            ],
            'assigned_on' => $this->assigned_on->toDateString(),
            'expected_return_on' => $this->expected_return_on?->toDateString(),
            'condition_on_issue' => $this->condition_on_issue->value,
            'condition_on_issue_label' => $this->condition_on_issue->label(),
            'handover_notes' => $this->handover_notes,
            'acknowledged' => $this->isAcknowledged(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601ZuluString(),
            'can_acknowledge' => $this->isActive() && ! $this->isAcknowledged(),
            'returned_on' => $this->returned_on?->toDateString(),
            'condition_on_return' => $this->condition_on_return?->value,
            'condition_on_return_label' => $this->condition_on_return?->label(),
            'return_notes' => $this->returned_on ? $this->return_notes : null,
            'overdue' => $this->isOverdue(),
        ];
    }
}
