<?php

namespace App\Http\Resources\V1;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\LeaveRequestDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own leave request (mobile app). "days" (per date) and
 * "approval" (history) are included when loaded (detail endpoint).
 *
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = now()->toDateString();

        return [
            'id' => $this->id,
            'leave_type' => ['id' => $this->leaveType->id, 'code' => $this->leaveType->code, 'name' => $this->leaveType->name, 'color' => $this->leaveType->color],
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'day_part' => $this->day_part->value,
            'hours' => $this->hours,
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'days' => $this->days,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'current_step' => $this->whenLoaded('latestApprovalRequest', fn () => $this->latestApprovalRequest?->isPending() ? $this->latestApprovalRequest->currentStep?->name : null),
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'can_cancel' => $this->status === LeaveRequestStatus::Pending || $this->status === LeaveRequestStatus::Approved,
            'cancel_needs_approval' => $this->status === LeaveRequestStatus::Approved
                && (! setting('leave.cancel_before_start_directly') || $this->start_date->toDateString() <= $today),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn (LeaveRequestAttachment $file): array => [
                'id' => $file->id, 'name' => $file->file_name, 'mime' => $file->file_mime, 'size' => $file->file_size,
                'url' => route('api.v1.me.leave.requests.attachments.show', [$this->id, $file->id]),
            ])->all()),
            'day_breakdown' => $this->whenLoaded('requestDays', fn () => $this->requestDays->map(fn (LeaveRequestDay $day): array => [
                'date' => $day->date->toDateString(), 'amount' => $day->amount, 'is_counted' => $day->is_counted, 'kind' => $day->day_kind,
            ])->all()),
            'approval' => $this->whenLoaded('approvalRequests', fn () => $this->approvalRequests->flatMap->actions->map(fn ($action): array => [
                'action' => $action->action->value, 'step' => $action->step_name, 'reason' => $action->reason, 'at' => $action->created_at?->toIso8601ZuluString(),
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'decided_at' => $this->decided_at?->toIso8601ZuluString(),
        ];
    }
}
