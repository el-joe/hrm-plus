<?php

namespace App\Actions\Training;

use App\Enums\TrainingEnrolmentStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ReferenceNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Training sessions: create / edit (capacity can never drop below the seats taken),
 * mark completed once started, and cancel (pending and enrolled places are cancelled
 * with the session, their approval runs closed).
 */
class ManageTrainingSession
{
    public function __construct(
        private readonly ReferenceNumbers $references,
        private readonly ManageTrainingEnrolment $enrolments,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): TrainingSession
    {
        return DB::transaction(fn (): TrainingSession => TrainingSession::create([
            ...$data,
            'reference' => $this->references->next('TRS'),
            'status' => TrainingSessionStatus::Scheduled,
            'created_by' => $actor->id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(TrainingSession $session, array $data): TrainingSession
    {
        if ($session->status === TrainingSessionStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => __('training.validation.session_cancelled')]);
        }

        return DB::transaction(function () use ($session, $data): TrainingSession {
            $session = TrainingSession::query()->lockForUpdate()->findOrFail($session->id);
            $taken = $session->seatsTaken();

            if ((int) $data['capacity'] < $taken) {
                throw ValidationException::withMessages(['capacity' => __('training.validation.capacity_below_taken', ['count' => $taken])]);
            }

            $session->update($data);

            return $session;
        });
    }

    /**
     * @throws ValidationException
     */
    public function complete(TrainingSession $session, User $actor): void
    {
        if (! $session->isScheduled() || $session->start_date->gt(today())) {
            throw ValidationException::withMessages(['status' => __('training.validation.cannot_complete')]);
        }

        $session->forceFill(['status' => TrainingSessionStatus::Completed])->save();
        $this->audit->log('training.session_completed', $session, actor: $actor);
    }

    /**
     * @throws ValidationException
     */
    public function cancel(TrainingSession $session, User $actor, string $reason): void
    {
        if (! $session->isScheduled()) {
            throw ValidationException::withMessages(['reason' => __('training.validation.cannot_cancel_session')]);
        }

        DB::transaction(function () use ($session, $actor, $reason): void {
            $session->forceFill(['status' => TrainingSessionStatus::Cancelled])->save();

            TrainingEnrolment::query()->withoutGlobalScopes()->where('training_session_id', $session->id)
                ->whereIn('status', [TrainingEnrolmentStatus::Pending, TrainingEnrolmentStatus::Enrolled])->get()
                ->each(function (TrainingEnrolment $enrolment) use ($actor, $reason): void {
                    if ($enrolment->isPending() || $enrolment->session->start_date->gt(today())) {
                        $this->enrolments->cancel($enrolment, $actor, $reason);
                    }
                });

            $this->audit->log('training.session_cancelled', $session, meta: ['reason' => $reason], actor: $actor);
        });
    }
}
