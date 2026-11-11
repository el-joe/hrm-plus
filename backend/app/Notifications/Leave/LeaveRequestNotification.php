<?php

namespace App\Notifications\Leave;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Leave request events, database + mail (mail when setting notifications.email_enabled):
 *
 * - to approvers: awaiting_approval (a request or a cancellation reached their step);
 * - to the employee (and the admin who filed it on their behalf): submitted,
 *   approved, rejected, cancelled, cancellation_approved, cancellation_rejected.
 *
 * Admin recipients get the admin request page, employees the portal "My leaves".
 */
class LeaveRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['submitted', 'awaiting_approval', 'approved', 'rejected', 'cancelled', 'cancellation_approved', 'cancellation_rejected'];

    public function __construct(public readonly LeaveRequest $leaveRequest, public readonly string $kind, public readonly ?string $reason = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return setting('notifications.email_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);
        $mail = (new MailMessage)->subject($data['title'])->line($data['message']);

        if ($this->reason) {
            $mail->line(__('leave.notifications.reason', ['reason' => $this->reason], $notifiable->locale));
        }

        return $mail->action(__('leave.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, leave_request_id: int, title: string, message: string, reason: ?string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $request = $this->leaveRequest->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'leaveType',
        ]);
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'kind' => 'leave_'.$this->kind,
            'leave_request_id' => $request->id,
            'title' => __('leave.notifications.'.$this->kind.'_title', [], $locale),
            'message' => __('leave.notifications.'.$this->kind.'_message', [
                'employee' => $request->employee->localized('name', $locale),
                'type' => $request->leaveType->localized('name', $locale),
                'period' => $request->period(),
                'days' => $request->days,
            ], $locale),
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.leave-requests.show', $request) : route('portal.leaves'),
        ];
    }
}
