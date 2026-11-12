<?php

namespace App\Notifications\Recruitment;

use App\Models\Interview;
use App\Models\User;
use App\Notifications\CentreNotification;

/**
 * An interviewer was added to a panel, or is reminded of an upcoming interview
 * (templates "interview_assigned", "interview_reminder").
 */
class InterviewAssigned extends CentreNotification
{
    public function __construct(public readonly Interview $interview, public readonly bool $reminder = false) {}

    public function event(): string
    {
        return $this->reminder ? 'interview_reminder' : 'interview_assigned';
    }

    protected function actionLabelKey(): string
    {
        return 'recruitment.mail.open_interview';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $interview = $this->interview->loadMissing(['application.candidate', 'application.vacancy']);
        $start = $interview->localStart();

        return [
            'candidate' => $interview->application->candidate->localized('name', $locale),
            'vacancy' => $interview->application->vacancy->localized('title', $locale),
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'timezone' => $interview->timezone,
        ];
    }

    /**
     * @return array{kind: string, interview_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => $this->event(),
            'interview_id' => $this->interview->getKey(),
            'url' => $notifiable instanceof User && $notifiable->isEmployee()
                ? route('portal.interviews.show', $this->interview)
                : route('admin.interviews.show', $this->interview),
        ];
    }
}
