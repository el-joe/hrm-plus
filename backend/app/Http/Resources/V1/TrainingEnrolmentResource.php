<?php

namespace App\Http\Resources\V1;

use App\Models\TrainingAttendance;
use App\Models\TrainingEnrolment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own training enrolment (mobile app). "attendance" and "approval"
 * are included when loaded (detail). Money and scores are DECIMAL strings.
 *
 * @mixin TrainingEnrolment
 */
class TrainingEnrolmentResource extends JsonResource
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
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'session' => new TrainingSessionResource($this->session),
            'reason' => $this->reason,
            'rejection_reason' => $this->rejection_reason,
            'current_step' => $this->whenLoaded('latestApprovalRequest', fn () => $this->latestApprovalRequest?->isPending() ? $this->latestApprovalRequest->currentStep?->name : null),
            'attendance_rate' => $this->relationLoaded('attendances') ? $this->attendanceRate() : null,
            'score' => $this->score === null ? null : (string) $this->score,
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
            'certificate_id' => $this->whenLoaded('certificate', fn () => $this->certificate?->id),
            'can_cancel' => $this->isPending(),
            'attendance' => $this->whenLoaded('attendances', fn () => $this->attendances->map(fn (TrainingAttendance $row): array => [
                'date' => $row->date->toDateString(), 'status' => $row->status->value, 'status_label' => $row->status->label(), 'note' => $row->note,
            ])->values()->all()),
            'approval' => $this->whenLoaded('approvalRequests', fn () => $this->approvalRequests->flatMap->actions->map(fn ($action): array => [
                'action' => $action->action->value, 'step' => $action->step_name, 'reason' => $action->reason, 'at' => $action->created_at?->toIso8601ZuluString(),
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
