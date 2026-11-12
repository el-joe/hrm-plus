<?php

namespace App\Services\HrRequests;

use App\Models\HrRequest;
use App\Models\HrRequestEvent;
use App\Models\User;

/**
 * Writes hr_request_events, the history shown on the request page (and, filtered to the
 * visible_to_employee entries, to the employee).
 */
class HrRequestHistory
{
    /**
     * Event types the employee sees in their status history.
     */
    public const EMPLOYEE_VISIBLE = ['submitted', 'status_changed', 'approval_approved', 'approval_rejected', 'letter_issued', 'changes_applied', 'changes_rejected'];

    public function record(HrRequest $request, string $type, ?User $actor, ?string $from = null, ?string $to = null, ?string $note = null): HrRequestEvent
    {
        return $request->events()->create([
            'type' => $type,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note !== null ? mb_substr($note, 0, 1000) : null,
            'visible_to_employee' => in_array($type, self::EMPLOYEE_VISIBLE, true),
            'actor_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
