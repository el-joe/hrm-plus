<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\ReviewStatus;
use App\Models\AttendanceMobileAttempt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The suspicious-attempt review queue: a flagged mobile attempt (mock
 * location, outside the shift window, time drift, repeated attempts, failed
 * face verification / not enrolled) is recorded only when a reviewer
 * approves it — at the time it was made — or rejected with a reason. Both
 * are audited; nothing flagged is ever recorded silently.
 */
class MobileAttemptReview
{
    public function __construct(private readonly MobileCheckIn $checkIn, private readonly AuditLogger $audit) {}

    public function approve(AttendanceMobileAttempt $attempt, User $reviewer, ?string $note): void
    {
        DB::transaction(function () use ($attempt, $reviewer, $note): void {
            $locked = $this->lockPending($attempt);
            $locked->forceFill(['review_status' => ReviewStatus::Approved, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            $this->checkIn->record($locked, $reviewer);
        });

        $this->audit->log('attendance.mobile_attempt_approved', $attempt, meta: ['employee_id' => $attempt->employee_id, 'reasons' => $attempt->reasonCodes(), 'note' => $note], actor: $reviewer);
    }

    public function reject(AttendanceMobileAttempt $attempt, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($attempt, $reviewer, $reason): void {
            $this->lockPending($attempt)
                ->forceFill(['review_status' => ReviewStatus::Rejected, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $reason])->save();
        });

        $this->audit->log('attendance.mobile_attempt_rejected', $attempt, meta: ['employee_id' => $attempt->employee_id, 'reasons' => $attempt->reasonCodes(), 'note' => $reason], actor: $reviewer);
    }

    private function lockPending(AttendanceMobileAttempt $attempt): AttendanceMobileAttempt
    {
        $locked = AttendanceMobileAttempt::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($attempt->id);

        if (! $locked->isPendingReview()) {
            throw ValidationException::withMessages(['review' => __('mobile.review.already_decided')]);
        }

        return $locked;
    }
}
