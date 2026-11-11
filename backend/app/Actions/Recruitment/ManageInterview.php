<?php

namespace App\Actions\Recruitment;

use App\Enums\InterviewDecision;
use App\Enums\InterviewStatus;
use App\Enums\StageCategory;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\Recruitment\InterviewAssigned;
use App\Notifications\Recruitment\InterviewInvitation;
use App\Services\Recruitment\CandidateNotifier;
use App\Services\Recruitment\Pipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Schedule, reschedule, cancel and close interviews.
 *
 * Scheduling invites the candidate (optional) and notifies the interviewers;
 * an application still in an "open" stage moves to the first "interview"
 * stage of its pipeline. Times are entered in a timezone and stored in UTC.
 */
class ManageInterview
{
    public function __construct(
        private readonly CandidateNotifier $notifier,
        private readonly Pipeline $pipeline,
        private readonly MoveApplication $move,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated InterviewRequest input
     */
    public function schedule(JobApplication $application, array $data, User $actor): Interview
    {
        if (! $application->isActive()) {
            throw ValidationException::withMessages(['date' => __('recruitment.validation.application_closed')]);
        }

        $interview = DB::transaction(function () use ($application, $data, $actor): Interview {
            $interview = $application->interviews()->create([
                ...$this->attributes($data, $application),
                'status' => InterviewStatus::Scheduled,
                'created_by' => $actor->getKey(),
            ]);
            $interview->interviewers()->sync($data['interviewer_ids']);

            return $interview;
        });

        $application->loadMissing(['stage', 'vacancy']);

        if ($application->stage->category === StageCategory::Open
            && ($stage = $this->pipeline->firstOfCategory($application->vacancy, StageCategory::Interview)) !== null) {
            $this->move->handle($application, $stage, $actor, __('recruitment.history.interview_scheduled'), notifyCandidate: false);
        }

        $this->notifyInterviewers($interview, $interview->interviewers()->get()->all());

        if ($data['notify_candidate'] ?? true) {
            $this->notifier->send($application->candidate, new InterviewInvitation($interview));
        }

        return $interview;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Interview $interview, array $data): Interview
    {
        if ($interview->status !== InterviewStatus::Scheduled) {
            throw ValidationException::withMessages(['date' => __('recruitment.validation.interview_not_scheduled')]);
        }

        $application = $interview->application;
        $previousStart = $interview->scheduled_at->copy();
        $previous = $interview->interviewers()->pluck('users.id')->all();

        DB::transaction(function () use ($interview, $data, $application): void {
            $interview->fill($this->attributes($data, $application));

            if ($interview->isDirty('scheduled_at')) {
                $interview->reminder_sent_at = null;
            }

            $interview->save();
            $interview->interviewers()->sync($data['interviewer_ids']);
        });

        $added = $interview->interviewers()->whereNotIn('users.id', $previous)->get()->all();
        $this->notifyInterviewers($interview, $added);

        if (! $previousStart->equalTo($interview->scheduled_at) && ($data['notify_candidate'] ?? true)) {
            $this->notifier->send($application->candidate, new InterviewInvitation($interview, rescheduled: true));
        }

        return $interview;
    }

    public function setStatus(Interview $interview, InterviewStatus $status, ?string $note = null): Interview
    {
        if ($interview->status !== InterviewStatus::Scheduled) {
            throw ValidationException::withMessages(['status' => __('recruitment.validation.interview_not_scheduled')]);
        }

        $interview->forceFill([
            'status' => $status,
            'notes' => $note !== null && $note !== '' ? trim(($interview->notes ? $interview->notes."\n" : '').$note) : $interview->notes,
        ])->save();

        return $interview;
    }

    public function decide(Interview $interview, InterviewDecision $decision, ?string $note = null): Interview
    {
        if (! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::Completed], true)) {
            throw ValidationException::withMessages(['decision' => __('recruitment.validation.interview_cancelled')]);
        }

        $interview->forceFill([
            'decision' => $decision,
            'decision_note' => $note,
            'status' => InterviewStatus::Completed,
        ])->save();

        return $interview;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, JobApplication $application): array
    {
        return [
            'type' => $data['type'],
            'scheduled_at' => Carbon::parse($data['date'].' '.$data['time'], $data['timezone'])->utc(),
            'timezone' => $data['timezone'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'mode' => $data['mode'],
            'location' => $data['location'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
            'evaluation_form_id' => $data['evaluation_form_id'] ?? $application->vacancy?->evaluation_form_id,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @param  list<User>  $interviewers
     */
    private function notifyInterviewers(Interview $interview, array $interviewers): void
    {
        $active = array_values(array_filter($interviewers, fn (User $user): bool => $user->isActive()));

        if ($active !== []) {
            Notification::send($active, new InterviewAssigned($interview));
        }
    }
}
