<?php

namespace App\Notifications\Recruitment;

use App\Models\Interview;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To an interviewer: assigned to an interview, or (reminder = true) the
 * interview is coming up. Database + mail (when e-mail notifications are on).
 * The link opens the admin interview page or, for employee accounts, the portal.
 */
class InterviewAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Interview $interview, public readonly bool $reminder = false) {}

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

        return (new MailMessage)->subject($data['title'])->line($data['message'])->action(__('recruitment.mail.open_interview'), $data['url']);
    }

    /**
     * @return array{kind: string, interview_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $interview = $this->interview->loadMissing(['application.candidate', 'application.vacancy']);
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $start = $interview->localStart();
        $key = $this->reminder ? 'reminder' : 'assigned';

        return [
            'kind' => $this->reminder ? 'interview_reminder' : 'interview_assigned',
            'interview_id' => $interview->getKey(),
            'title' => __("recruitment.notifications.interview_{$key}_title", [], $locale),
            'message' => __("recruitment.notifications.interview_{$key}_message", [
                'candidate' => $interview->application->candidate->localized('name', $locale),
                'vacancy' => $interview->application->vacancy->localized('title', $locale),
                'date' => $start->toDateString(), 'time' => $start->format('H:i'), 'timezone' => $interview->timezone,
            ], $locale),
            'url' => $notifiable instanceof User && $notifiable->isEmployee()
                ? route('portal.interviews.show', $interview)
                : route('admin.interviews.show', $interview),
        ];
    }
}
