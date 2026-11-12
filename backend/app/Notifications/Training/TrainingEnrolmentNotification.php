<?php

namespace App\Notifications\Training;

use App\Models\TrainingEnrolment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Training enrolment events, database + mail (mail when setting notifications.email_enabled):
 *
 * - to approvers: awaiting_approval;
 * - to the employee (and the admin who nominated them): nominated (enrolled by HR),
 *   submitted, approved, rejected, cancelled, completed.
 *
 * Admin recipients get the admin enrolment page, employees the portal "My training".
 */
class TrainingEnrolmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['nominated', 'submitted', 'awaiting_approval', 'approved', 'rejected', 'cancelled', 'completed'];

    public function __construct(public readonly TrainingEnrolment $enrolment, public readonly string $kind, public readonly ?string $reason = null) {}

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
            $mail->line(__('training.notifications.reason', ['reason' => $this->reason], $notifiable->locale));
        }

        return $mail->action(__('training.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, training_enrolment_id: int, title: string, message: string, reason: ?string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $enrolment = $this->enrolment->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'session.course',
        ]);
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin() && $notifiable->employee_id !== $enrolment->employee_id;

        return [
            'kind' => 'training_'.$this->kind,
            'training_enrolment_id' => $enrolment->id,
            'title' => __('training.notifications.'.$this->kind.'_title', [], $locale),
            'message' => __('training.notifications.'.$this->kind.'_message', [
                'employee' => $enrolment->employee->localized('name', $locale),
                'course' => $enrolment->session->course->localized('name', $locale),
                'date' => $enrolment->session->start_date->toDateString(),
            ], $locale),
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.training-enrolments.show', $enrolment) : route('portal.training'),
        ];
    }
}
