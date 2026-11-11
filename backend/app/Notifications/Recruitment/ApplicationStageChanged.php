<?php

namespace App\Notifications\Recruitment;

use App\Enums\StageCategory;
use App\Models\JobApplication;
use App\Models\RecruitmentStage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when an application enters a stage flagged "notify candidate".
 * Rejections use a courteous text and never include the internal reason.
 */
class ApplicationStageChanged extends CandidateMailNotification
{
    public function __construct(public readonly JobApplication $application, public readonly RecruitmentStage $stage) {}

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application->loadMissing(['candidate', 'vacancy']);
        $vacancy = $application->vacancy->title;
        $mail = (new MailMessage)->greeting(__('recruitment.mail.greeting', ['name' => $application->candidate->name]));

        return match ($this->stage->category) {
            StageCategory::Rejected => $mail->subject(__('recruitment.mail.rejected_subject', ['vacancy' => $vacancy]))
                ->line(__('recruitment.mail.rejected_line', ['vacancy' => $vacancy])),
            StageCategory::Hired => $mail->subject(__('recruitment.mail.hired_subject', ['vacancy' => $vacancy]))
                ->line(__('recruitment.mail.hired_line', ['vacancy' => $vacancy])),
            default => $mail->subject(__('recruitment.mail.stage_subject', ['vacancy' => $vacancy]))
                ->line(__('recruitment.mail.stage_line', ['vacancy' => $vacancy, 'stage' => $this->stage->name])),
        };
    }
}
