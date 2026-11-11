<?php

namespace App\Services\Recruitment;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\User;
use App\Notifications\Recruitment\InterviewAssigned;
use App\Notifications\Recruitment\InterviewInvitation;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds interviewers (database + mail) and the candidate (mail) of
 * interviews starting within recruitment.interview_reminder_hours. Each
 * interview is reminded once (reminder_sent_at; rescheduling re-arms it).
 */
class InterviewReminders
{
    public function __construct(private readonly CandidateNotifier $notifier) {}

    public function send(): int
    {
        $interviews = Interview::query()->withoutGlobalScopes()
            ->where('status', InterviewStatus::Scheduled)
            ->whereNull('reminder_sent_at')
            ->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addHours((int) setting('recruitment.interview_reminder_hours')))
            ->with(['interviewers', 'application.candidate', 'application.vacancy'])
            ->get();

        foreach ($interviews as $interview) {
            $interviewers = $interview->interviewers->filter(fn (User $user): bool => $user->isActive());

            if ($interviewers->isNotEmpty()) {
                Notification::send($interviewers, new InterviewAssigned($interview, reminder: true));
            }

            $this->notifier->send($interview->application->candidate, new InterviewInvitation($interview, reminder: true));
            $interview->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        }

        return $interviews->count();
    }
}
