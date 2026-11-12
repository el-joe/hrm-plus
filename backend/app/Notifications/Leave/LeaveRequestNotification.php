<?php

namespace App\Notifications\Leave;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\CentreNotification;

/**
 * Leave request events (texts: notification templates "leave_*"):
 *
 * - to approvers: awaiting_approval (a request or a cancellation reached their step);
 * - to the employee (and the admin who filed it on their behalf): submitted,
 *   approved, rejected, cancelled, cancellation_approved, cancellation_rejected.
 *
 * Admin recipients get the admin request page, employees the portal "My leaves".
 */
class LeaveRequestNotification extends CentreNotification
{
    public const KINDS = ['submitted', 'awaiting_approval', 'approved', 'rejected', 'cancelled', 'cancellation_approved', 'cancellation_rejected'];

    public function __construct(public readonly LeaveRequest $leaveRequest, public readonly string $kind, public readonly ?string $reason = null) {}

    public function event(): string
    {
        return 'leave_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'leave.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $request = $this->request();

        return [
            'employee' => $request->employee->localized('name', $locale),
            'type' => $request->leaveType->localized('name', $locale),
            'period' => $request->period(),
            'days' => $request->days,
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{kind: string, leave_request_id: int, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'kind' => 'leave_'.$this->kind,
            'leave_request_id' => $this->leaveRequest->id,
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.leave-requests.show', $this->leaveRequest) : route('portal.leaves'),
        ];
    }

    private function request(): LeaveRequest
    {
        return $this->leaveRequest->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'leaveType',
        ]);
    }
}
