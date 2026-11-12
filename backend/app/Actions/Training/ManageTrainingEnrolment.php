<?php

namespace App\Actions\Training;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\SkillSource;
use App\Enums\TrainingEnrolmentSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\EmployeeSkill;
use App\Models\TrainingCourseSkill;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\Training\TrainingEnrolmentNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of a place on a training session.
 *
 * nominate(): by an admin. Holders of training.manage (HR) enrol the employee at
 *   once; other nominators (managers holding training.create) send the nomination
 *   through the "training_enrolment" approval workflow.
 * request():  the employee's own request (portal / API), always through approval,
 *   only while the setting training.self_requests_enabled is on.
 * decide():   one approval step; approving needs a free seat. The outcome is applied by
 *   applyApprovalOutcome() (listener App\Listeners\SyncTrainingApprovals), which
 *   re-checks the seat under a lock.
 * cancel():   a pending request, or an enrolment whose session has not started.
 * recordOutcome(): completed / failed / no-show with a score. "Completed" needs the
 *   attendance rate of setting training.completion_min_attendance_percent; it raises
 *   the employee's skills to the course's levels (never lowers them) and issues the
 *   course certificate.
 *
 * Seats are counted across the company with the session row locked, so two
 * concurrent enrolments cannot both take the last seat. An employee has one row
 * per session: a cancelled / rejected row is reused by a new request.
 */
class ManageTrainingEnrolment
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApprovalRecipients $recipients,
        private readonly ManageTrainingCertificate $certificates,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function nominate(TrainingSession $session, Employee $employee, User $actor, ?string $reason = null): TrainingEnrolment
    {
        $direct = $actor->can('training.manage');

        return $this->open($session, $employee, $actor, $direct ? TrainingEnrolmentSource::Hr : TrainingEnrolmentSource::Manager, $reason, $direct);
    }

    /**
     * @throws ValidationException
     */
    public function request(TrainingSession $session, Employee $employee, User $user, ?string $reason = null): TrainingEnrolment
    {
        if (! setting('training.self_requests_enabled')) {
            throw ValidationException::withMessages(['training_session_id' => __('training.validation.requests_disabled')]);
        }

        return $this->open($session, $employee, $user, TrainingEnrolmentSource::Employee, $reason, false);
    }

    /**
     * @throws ValidationException
     */
    public function decide(TrainingEnrolment $enrolment, User $approver, bool $approve, ?string $reason = null): void
    {
        $approval = $enrolment->latestApprovalRequest()->first();

        if ($approval === null || ! $approval->isPending() || ! $enrolment->isPending()) {
            throw ValidationException::withMessages(['reason' => __('training.validation.not_pending')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $enrolment->employee_id) {
            throw ValidationException::withMessages(['reason' => __('training.validation.own_enrolment')]);
        }

        if ($approve && $enrolment->session()->first()->seatsLeft() < 1) {
            throw ValidationException::withMessages(['reason' => __('training.validation.session_full')]);
        }

        try {
            $approval = $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        if ($approval->isPending()) {
            $this->notifyApprovers($enrolment);
        }
    }

    /**
     * Apply a closed approval run (listener of ApprovalRequestApproved / -Rejected).
     */
    public function applyApprovalOutcome(TrainingEnrolment $enrolment, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if (! $enrolment->isPending()) {
            return;
        }

        $enrolled = DB::transaction(function () use ($enrolment, $approved, $decidedBy, $reason): bool {
            $session = TrainingSession::query()->lockForUpdate()->findOrFail($enrolment->training_session_id);
            $seat = $approved && $session->seatsLeft() > 0;

            $enrolment->forceFill([
                'status' => $seat ? TrainingEnrolmentStatus::Enrolled : TrainingEnrolmentStatus::Rejected,
                'decided_at' => now(),
                'decided_by' => $decidedBy?->id,
                'rejection_reason' => $seat ? null : ($approved ? __('training.validation.session_full') : $reason),
            ])->save();

            return $seat;
        });

        $this->notifyEmployee($enrolment, $enrolled ? 'approved' : 'rejected', $decidedBy, $enrolled ? null : $enrolment->rejection_reason);
    }

    /**
     * @throws ValidationException
     */
    public function cancel(TrainingEnrolment $enrolment, User $actor, ?string $reason = null): void
    {
        $session = $enrolment->session()->first();
        $cancellable = $enrolment->isPending() || ($enrolment->isEnrolled() && $session->start_date->gt(today()));

        if (! $cancellable) {
            throw ValidationException::withMessages(['reason' => __('training.validation.not_cancellable')]);
        }

        DB::transaction(function () use ($enrolment, $actor, $reason): void {
            $this->closeApproval($enrolment->latestApprovalRequest()->first(), $actor, $reason);
            $enrolment->forceFill(['status' => TrainingEnrolmentStatus::Cancelled, 'cancelled_at' => now(), 'rejection_reason' => $reason])->save();
            $this->audit->log('training.enrolment_cancelled', $enrolment, meta: ['reason' => $reason], actor: $actor);
        });

        $this->notifyEmployee($enrolment, 'cancelled', $actor, $reason);
    }

    /**
     * Record the outcome of a participant once the session has started.
     *
     * @throws ValidationException
     */
    public function recordOutcome(TrainingEnrolment $enrolment, TrainingEnrolmentStatus $outcome, ?string $score, ?string $note, User $actor): TrainingEnrolment
    {
        if (! in_array($outcome, TrainingEnrolmentStatus::outcomes(), true)) {
            throw ValidationException::withMessages(['status' => __('training.validation.invalid_outcome')]);
        }

        if (! in_array($enrolment->status, [TrainingEnrolmentStatus::Enrolled, ...TrainingEnrolmentStatus::outcomes()], true)) {
            throw ValidationException::withMessages(['status' => __('training.validation.not_enrolled')]);
        }

        $session = $enrolment->session()->with('course.courseSkills')->firstOrFail();

        if ($session->start_date->gt(today())) {
            throw ValidationException::withMessages(['status' => __('training.validation.not_started')]);
        }

        if ($outcome === TrainingEnrolmentStatus::Completed) {
            $required = (string) (int) setting('training.completion_min_attendance_percent');
            $rate = $enrolment->load('attendances')->attendanceRate(count($session->days()));

            if (bccomp($rate, $required, 2) === -1) {
                throw ValidationException::withMessages(['status' => __('training.validation.attendance_too_low', ['rate' => $rate, 'required' => $required])]);
            }
        }

        DB::transaction(function () use ($enrolment, $outcome, $score, $note, $actor, $session): void {
            $enrolment->forceFill([
                'status' => $outcome,
                'score' => filled($score) ? bcadd((string) $score, '0', 2) : null,
                'completion_note' => $note,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ])->save();

            if ($outcome === TrainingEnrolmentStatus::Completed) {
                $this->raiseSkills($enrolment, $session->course->courseSkills->all(), $actor);

                if ($session->course->issues_certificate && ! $enrolment->certificate()->exists()) {
                    $this->certificates->issueForEnrolment($enrolment, $session, $actor);
                }
            }

            $this->audit->log('training.outcome_recorded', $enrolment, meta: ['outcome' => $outcome->value], actor: $actor);
        });

        if ($outcome === TrainingEnrolmentStatus::Completed) {
            $this->notifyEmployee($enrolment, 'completed', $actor);
        }

        return $enrolment->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function open(TrainingSession $session, Employee $employee, User $actor, TrainingEnrolmentSource $source, ?string $reason, bool $direct): TrainingEnrolment
    {
        if (! $employee->isActive()) {
            throw ValidationException::withMessages(['employee_id' => __('training.validation.employee_inactive')]);
        }

        $enrolment = DB::transaction(function () use ($session, $employee, $actor, $source, $reason, $direct): TrainingEnrolment {
            $session = TrainingSession::query()->with('course')->lockForUpdate()->findOrFail($session->id);

            if (! $session->acceptsEnrolments()) {
                throw ValidationException::withMessages(['training_session_id' => __('training.validation.session_closed')]);
            }

            $existing = TrainingEnrolment::query()->withoutGlobalScopes()
                ->where('training_session_id', $session->id)->where('employee_id', $employee->id)->lockForUpdate()->first();

            if ($existing !== null && ! in_array($existing->status, [TrainingEnrolmentStatus::Cancelled, TrainingEnrolmentStatus::Rejected], true)) {
                throw ValidationException::withMessages(['employee_id' => __('training.validation.already_enrolled')]);
            }

            if ($session->seatsLeft() < 1) {
                throw ValidationException::withMessages(['training_session_id' => __('training.validation.session_full')]);
            }

            $enrolment = $existing ?? new TrainingEnrolment(['training_session_id' => $session->id, 'employee_id' => $employee->id]);
            $enrolment->forceFill([
                'source' => $source,
                'status' => $direct ? TrainingEnrolmentStatus::Enrolled : TrainingEnrolmentStatus::Pending,
                'cost' => $session->participantCost(),
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'reason' => $reason,
                'requested_by' => $actor->id,
                'decided_at' => $direct ? now() : null,
                'decided_by' => $direct ? $actor->id : null,
                'rejection_reason' => null,
                'cancelled_at' => null,
                'score' => null,
                'completed_at' => null,
                'completed_by' => null,
            ])->save();

            if (! $direct) {
                try {
                    $this->approvals->submit($enrolment, TrainingEnrolment::WORKFLOW, $actor);
                } catch (ApprovalException $exception) {
                    throw ValidationException::withMessages(['training_session_id' => $exception->getMessage()]);
                }
            }

            $this->audit->log($direct ? 'training.enrolled' : 'training.enrolment_requested', $enrolment, meta: [
                'session' => $session->reference, 'source' => $source->value,
            ], actor: $actor);

            return $enrolment;
        });

        if ($direct) {
            $this->notifyEmployee($enrolment, 'nominated', $actor);
        } else {
            $this->notifyApprovers($enrolment);
            $this->notifyEmployee($enrolment, 'submitted', $actor);
        }

        return $enrolment->refresh();
    }

    /**
     * Raise employee skills to the course levels; an existing higher level is kept.
     *
     * @param  list<TrainingCourseSkill>  $courseSkills
     */
    private function raiseSkills(TrainingEnrolment $enrolment, array $courseSkills, User $actor): void
    {
        foreach ($courseSkills as $courseSkill) {
            $current = EmployeeSkill::query()->withoutGlobalScopes()
                ->where('employee_id', $enrolment->employee_id)->where('skill_id', $courseSkill->skill_id)->first();

            if ($current !== null && $current->level >= $courseSkill->level) {
                continue;
            }

            EmployeeSkill::query()->withoutGlobalScopes()->updateOrCreate(
                ['employee_id' => $enrolment->employee_id, 'skill_id' => $courseSkill->skill_id],
                ['level' => $courseSkill->level, 'source' => SkillSource::Training, 'training_enrolment_id' => $enrolment->id,
                    'assessed_on' => today()->toDateString(), 'updated_by' => $actor->id],
            );
        }
    }

    private function closeApproval(?ApprovalRequest $approval, User $user, ?string $reason): void
    {
        if ($approval === null || ! $approval->isPending()) {
            return;
        }

        if ($approval->requested_by === $user->id) {
            $this->approvals->cancel($approval, $user, $reason);

            return;
        }

        $approval->actions()->create([
            'approval_workflow_step_id' => $approval->current_step_id, 'step_sequence' => $approval->currentStep?->sequence,
            'step_name' => $approval->currentStep?->name, 'action' => ApprovalActionType::Cancelled, 'acted_by' => $user->id, 'reason' => $reason,
        ]);
        $approval->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);
        $this->audit->log('approval.cancelled', $approval, actor: $user);
    }

    private function notifyApprovers(TrainingEnrolment $enrolment): void
    {
        $approval = $enrolment->latestApprovalRequest()->first();

        if ($approval === null) {
            return;
        }

        foreach ($this->recipients->for($approval)->reject(fn (User $user): bool => $user->employee_id === $enrolment->employee_id) as $approver) {
            $approver->notify(new TrainingEnrolmentNotification($enrolment, 'awaiting_approval'));
        }
    }

    /**
     * The employee's own account(s) and the admin who nominated them — never the actor.
     */
    private function notifyEmployee(TrainingEnrolment $enrolment, string $kind, ?User $actor, ?string $reason = null): void
    {
        $users = User::query()->where('employee_id', $enrolment->employee_id)->get();

        if ($enrolment->requested_by !== null) {
            $users = $users->push(User::query()->find($enrolment->requested_by))->filter();
        }

        foreach ($users->unique('id') as $user) {
            if ($actor !== null && $user->id === $actor->id) {
                continue;
            }

            $user->notify(new TrainingEnrolmentNotification($enrolment, $kind, $reason));
        }
    }
}
