<?php

namespace App\Notifications\Recruitment;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicationReceived extends CandidateMailNotification
{
    public function __construct(public readonly JobApplication $application) {}

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application->loadMissing(['candidate', 'vacancy']);

        return (new MailMessage)
            ->subject(__('recruitment.mail.received_subject', ['vacancy' => $application->vacancy->title]))
            ->greeting(__('recruitment.mail.greeting', ['name' => $application->candidate->name]))
            ->line(__('recruitment.mail.received_line', ['vacancy' => $application->vacancy->title, 'reference' => $application->vacancy->reference]))
            ->line(__('recruitment.mail.received_next'));
    }
}
