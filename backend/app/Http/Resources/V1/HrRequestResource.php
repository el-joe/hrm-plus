<?php

namespace App\Http\Resources\V1;

use App\Models\HrProfileChange;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\HrRequestEvent;
use App\Models\HrRequestFieldValue;
use App\Models\HrRequestMessage;
use App\Services\HrRequests\ProfileChangeFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own HR request (mobile app). The detail (when loaded through PortalHrRequests::find) adds
 * the employee-visible thread, files, status history and profile changes. Internal notes and HR-only
 * files are never part of it: only the employeeMessages / employeeAttachments relations are read.
 *
 * @mixin HrRequest
 */
class HrRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = app(ProfileChangeFields::class);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'category' => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
                'type' => $this->category->type->value,
            ],
            'source' => $this->source->value,
            'letter_language' => $this->letter_language,
            'submitted_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'due_at' => $this->due_at?->toIso8601ZuluString(),
            'resolved_at' => $this->resolved_at?->toIso8601ZuluString(),
            'can_cancel' => $this->status->isCancellableByEmployee(),
            'can_reply' => ! $this->isClosed(),
            'description' => $this->when($this->relationLoaded('employeeMessages'), $this->description),
            'resolution_note' => $this->when($this->relationLoaded('employeeMessages'), $this->resolution_note),
            'rejection_reason' => $this->when($this->relationLoaded('employeeMessages'), $this->rejection_reason),
            'fields' => $this->whenLoaded('fieldValues', fn (): array => $this->fieldValues->map(fn (HrRequestFieldValue $value): array => [
                'label' => $value->label(), 'value' => $value->value,
            ])->values()->all()),
            'messages' => $this->whenLoaded('employeeMessages', fn (): array => $this->employeeMessages->map(fn (HrRequestMessage $message): array => [
                'id' => $message->id,
                'type' => $message->type->value,
                'body' => $message->body,
                'from_employee' => $message->from_employee,
                'author_name' => $message->from_employee ? null : ($message->author?->name ?? __('hr_requests.portal.hr_team')),
                'created_at' => $message->created_at?->toIso8601ZuluString(),
            ])->values()->all()),
            'attachments' => $this->whenLoaded('employeeAttachments', fn (): array => $this->employeeAttachments->map(fn (HrRequestAttachment $file): array => [
                'id' => $file->id,
                'name' => $file->file_name,
                'mime' => $file->file_mime,
                'size' => $file->file_size,
                'is_letter' => $file->is_letter,
                'message_id' => $file->hr_request_message_id,
                'uploaded_at' => $file->created_at?->toIso8601ZuluString(),
                'download_url' => route('api.v1.me.requests.attachments.show', [$this->id, $file->id]),
            ])->values()->all()),
            'history' => $this->whenLoaded('events', fn (): array => $this->events->map(fn (HrRequestEvent $event): array => [
                'type' => $event->type,
                'description' => $event->description(),
                'created_at' => $event->created_at?->toIso8601ZuluString(),
            ])->values()->all()),
            'profile_changes' => $this->whenLoaded('profileChanges', fn (): array => $this->profileChanges->map(fn (HrProfileChange $change): array => [
                'field' => $change->field,
                'label' => $fields->label($change->field),
                'old_value' => $fields->display($change->field, $change->old_value),
                'new_value' => $fields->display($change->field, $change->new_value),
                'status' => $change->status->value,
                'status_label' => $change->status->label(),
            ])->values()->all()),
        ];
    }
}
