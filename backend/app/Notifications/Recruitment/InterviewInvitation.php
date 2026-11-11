<?php

namespace App\Notifications\Recruitment;

use App\Enums\InterviewMode;
use App\Models\Interview;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Interview invitation (and reminder, $reminder = true) to the candidate,
 * with the time in the interview's own timezone.
 */
class InterviewInvitation extends CandidateMailNotification
{
    public function __construct(public readonly Interview $interview, public readonly bool $reminder = false, public readonly bool $rescheduled = false) {}

    public function toMail(object $notifiable): MailMessage
    {
        $interview = $this->interview->loadMissing(['application.candidate', 'application.vacancy']);
        $vacancy = $interview->application->vacancy->title;
        $start = $interview->localStart();
        $key = $this->reminder ? 'reminder' : ($this->rescheduled ? 'rescheduled' : 'invitation');

        $mail = (new MailMessage)
            ->subject(__("recruitment.mail.interview_{$key}_subject", ['vacancy' => $vacancy]))
            ->greeting(__('recruitment.mail.greeting', ['name' => $interview->application->candidate->name]))
            ->line(__("recruitment.mail.interview_{$key}_line", ['vacancy' => $vacancy]))
            ->line(__('recruitment.mail.interview_when', [
                'date' => $start->toDateString(), 'time' => $start->format('H:i'), 'timezone' => $interview->timezone,
                'duration' => $interview->duration_minutes,
            ]))
            ->line(__('recruitment.mail.interview_mode', ['mode' => $interview->mode->label()]));

        if ($interview->mode === InterviewMode::Online && $interview->meeting_url) {
            $mail->action(__('recruitment.mail.join_meeting'), $interview->meeting_url);
        } elseif ($interview->location) {
            $mail->line(__('recruitment.mail.interview_location', ['location' => $interview->location]));
        }

        return $mail;
    }
}
