<?php

namespace App\Actions\Performance;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\PerformanceReviewStatus;
use App\Exceptions\ApprovalException;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCompetency;
use App\Models\RatingScaleLevel;
use App\Models\User;
use App\Notifications\Performance\PerformanceReviewNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Performance\PerformanceScoring;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of a performance review (see PerformanceReviewStatus):
 *
 * submitSelf():     the employee rates their goals and competencies (portal / API) →
 *                   manager_assessment; the reviewer is notified.
 * skipSelf():       HR moves a review on without a self-assessment.
 * saveAssessment(): the reviewer (or HR) rates; with $submit every counted goal and
 *                   competency must be rated and the goal weights must total 100. The
 *                   scores and the final rating band are calculated (PerformanceScoring)
 *                   and the review goes to HR calibration ("performance_review" workflow).
 * decide():         one approval step; approving may override the final band (calibration).
 *                   The outcome is applied by applyApprovalOutcome() (listener
 *                   SyncPerformanceApprovals): approved → acknowledgement (employee
 *                   notified), rejected → back to manager_assessment with the reason.
 * acknowledge():    the employee confirms they have read it (optional comment) → completed.
 * cancel():         HR, for an open review.
 *
 * $data shape for ratings: ['goals' => [goal_id => ['rating' => '4', 'comment' => '...']],
 * 'competencies' => [review_competency_id => ['rating' => '3', 'comment' => '...']]].
 */
class ManagePerformanceReview
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApprovalRecipients $recipients,
        private readonly PerformanceScoring $scoring,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{goals?: array<int|string, array{rating?: mixed, comment?: ?string}>, competencies?: array<int|string, array{rating?: mixed, comment?: ?string}>, comments?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function submitSelf(PerformanceReview $review, array $data, User $user): PerformanceReview
    {
        if ($user->employee_id !== $review->employee_id) {
            throw ValidationException::withMessages(['review' => __('performance.validation.not_your_review')]);
        }

        $this->ensureStatus($review, PerformanceReviewStatus::SelfAssessment);

        DB::transaction(function () use ($review, $data, $user): void {
            $this->storeRatings($review, $data, 'self', true);
            $scores = $this->scoring->scoreReview($review, 'self');

            $review->forceFill([
                'self_goals_score' => $scores['goals'],
                'self_competencies_score' => $scores['competencies'],
                'self_overall_score' => $scores['overall'],
                'self_comments' => $data['comments'] ?? null,
                'self_submitted_at' => now(),
                'status' => PerformanceReviewStatus::ManagerAssessment,
            ])->save();

            $this->audit->log('performance.self_submitted', $review, meta: ['score' => $scores['overall']], actor: $user);
        });

        $this->notifyReviewer($review, 'self_submitted');

        return $review->refresh();
    }

    /**
     * @throws ValidationException
     */
    public function skipSelf(PerformanceReview $review, User $user): PerformanceReview
    {
        $this->ensureStatus($review, PerformanceReviewStatus::SelfAssessment);

        $review->forceFill(['status' => PerformanceReviewStatus::ManagerAssessment])->save();
        $this->audit->log('performance.self_skipped', $review, actor: $user);
        $this->notifyReviewer($review, 'launched_reviewer');

        return $review;
    }

    /**
     * @param  array{goals?: array<int|string, array{rating?: mixed, comment?: ?string}>, competencies?: array<int|string, array{rating?: mixed, comment?: ?string}>, strengths?: ?string, improvements?: ?string, comments?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function saveAssessment(PerformanceReview $review, array $data, User $user, bool $submit): PerformanceReview
    {
        $this->ensureStatus($review, PerformanceReviewStatus::ManagerAssessment);

        if ($user->employee_id !== null && $user->employee_id === $review->employee_id) {
            throw ValidationException::withMessages(['review' => __('performance.validation.own_review')]);
        }

        DB::transaction(function () use ($review, $data, $user, $submit): void {
            $this->storeRatings($review, $data, 'manager', $submit);

            if ($submit) {
                $this->checkGoalWeights($review);
            }

            $scores = $this->scoring->scoreReview($review, 'manager');
            $level = $this->scoring->levelFor($review->cycle->ratingScale()->with('levels')->first(), $scores['overall']);

            $review->forceFill([
                'goals_score' => $scores['goals'],
                'competencies_score' => $scores['competencies'],
                'overall_score' => $scores['overall'],
                'final_rating_level_id' => $level?->id,
                'calibrated' => false,
                'calibration_note' => null,
                'manager_strengths' => $data['strengths'] ?? $review->manager_strengths,
                'manager_improvements' => $data['improvements'] ?? $review->manager_improvements,
                'manager_comments' => $data['comments'] ?? $review->manager_comments,
            ])->save();

            if (! $submit) {
                return;
            }

            $review->forceFill([
                'status' => PerformanceReviewStatus::PendingApproval,
                'manager_submitted_at' => now(),
                'manager_submitted_by' => $user->id,
            ])->save();

            try {
                $this->approvals->submit($review, PerformanceReview::WORKFLOW, $user);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['review' => $exception->getMessage()]);
            }

            $this->audit->log('performance.manager_submitted', $review, meta: ['score' => $scores['overall'], 'rating' => $level?->value], actor: $user);
        });

        if ($submit) {
            $this->notifyApprovers($review->refresh());
        }

        return $review->refresh();
    }

    /**
     * One approval step. When approving, $calibratedLevelId (a level of the cycle's scale)
     * replaces the band calculated from the score; a note is then required.
     *
     * @throws ValidationException
     */
    public function decide(PerformanceReview $review, User $approver, bool $approve, ?string $reason = null, ?int $calibratedLevelId = null): void
    {
        $approval = $review->latestApprovalRequest()->first();

        if ($approval === null || ! $approval->isPending() || $review->status !== PerformanceReviewStatus::PendingApproval) {
            throw ValidationException::withMessages(['reason' => __('performance.validation.not_pending')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $review->employee_id) {
            throw ValidationException::withMessages(['reason' => __('performance.validation.own_review')]);
        }

        DB::transaction(function () use ($review, $approval, $approver, $approve, $reason, $calibratedLevelId): void {
            if ($approve && $calibratedLevelId !== null && $calibratedLevelId !== $review->final_rating_level_id) {
                $level = RatingScaleLevel::query()->where('rating_scale_id', $review->cycle->rating_scale_id)->find($calibratedLevelId);

                if ($level === null) {
                    throw ValidationException::withMessages(['calibrated_level_id' => __('performance.validation.level_invalid')]);
                }

                if (blank($reason)) {
                    throw ValidationException::withMessages(['reason' => __('performance.validation.calibration_note')]);
                }

                $review->forceFill(['final_rating_level_id' => $level->id, 'calibrated' => true, 'calibration_note' => $reason])->save();
                $this->audit->log('performance.calibrated', $review, meta: ['level' => $level->value], actor: $approver);
            }

            try {
                $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
            }
        });

        $review->refresh();

        if ($review->status === PerformanceReviewStatus::PendingApproval) {
            $this->notifyApprovers($review);
        }
    }

    /**
     * Final outcome of the approval run (listener SyncPerformanceApprovals).
     */
    public function applyApprovalOutcome(PerformanceReview $review, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if ($review->status !== PerformanceReviewStatus::PendingApproval) {
            return;
        }

        if ($approved) {
            $review->forceFill([
                'status' => PerformanceReviewStatus::Acknowledgement,
                'approved_at' => now(),
                'approved_by' => $decidedBy?->id,
            ])->save();

            foreach (User::query()->where('employee_id', $review->employee_id)->get() as $user) {
                $user->notify(new PerformanceReviewNotification($review, 'approved'));
            }

            return;
        }

        $review->forceFill(['status' => PerformanceReviewStatus::ManagerAssessment, 'calibrated' => false, 'calibration_note' => null])->save();
        $this->notifyReviewer($review, 'returned', $reason);
    }

    /**
     * @throws ValidationException
     */
    public function acknowledge(PerformanceReview $review, User $user, ?string $comment): PerformanceReview
    {
        if ($user->employee_id !== $review->employee_id) {
            throw ValidationException::withMessages(['review' => __('performance.validation.not_your_review')]);
        }

        $this->ensureStatus($review, PerformanceReviewStatus::Acknowledgement);

        $review->forceFill([
            'status' => PerformanceReviewStatus::Completed,
            'acknowledged_at' => now(),
            'employee_comment' => filled($comment) ? $comment : null,
        ])->save();
        $this->audit->log('performance.acknowledged', $review, actor: $user);

        return $review;
    }

    /**
     * @throws ValidationException
     */
    public function cancel(PerformanceReview $review, User $user, string $reason): PerformanceReview
    {
        if (! $review->status->isOpen()) {
            throw ValidationException::withMessages(['reason' => __('performance.validation.not_cancellable')]);
        }

        DB::transaction(function () use ($review, $user, $reason): void {
            $approval = $review->latestApprovalRequest()->first();

            if ($approval !== null && $approval->isPending()) {
                $approval->actions()->create([
                    'approval_workflow_step_id' => $approval->current_step_id, 'step_sequence' => $approval->currentStep?->sequence,
                    'step_name' => $approval->currentStep?->name, 'action' => ApprovalActionType::Cancelled, 'acted_by' => $user->id, 'reason' => $reason,
                ]);
                $approval->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);
            }

            $review->forceFill(['status' => PerformanceReviewStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id])->save();
            $this->audit->log('performance.review_cancelled', $review, meta: ['reason' => $reason], actor: $user);
        });

        return $review;
    }

    /**
     * The values a rating may take: the levels of the cycle's scale.
     *
     * @return list<string>
     */
    public function allowedRatings(PerformanceReview $review): array
    {
        return $review->cycle->ratingScale->levels->map(fn (RatingScaleLevel $level): string => (string) $level->value)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function storeRatings(PerformanceReview $review, array $data, string $side, bool $complete): void
    {
        $allowed = $this->allowedRatings($review);
        $ratingColumn = $side.'_rating';
        $commentColumn = $side.'_comment';
        $goalsInput = $data['goals'] ?? [];
        $competenciesInput = $data['competencies'] ?? [];
        $errors = [];

        /** @var Collection<int, PerformanceGoal> $goals */
        $goals = $review->goalsQuery()->counted()->get();

        foreach ($goals as $goal) {
            $input = $goalsInput[$goal->id] ?? [];
            $rating = $this->rating($input['rating'] ?? null, $allowed);

            if ($rating === false) {
                $errors['goals.'.$goal->id.'.rating'] = __('performance.validation.rating_invalid');

                continue;
            }

            if ($rating === null && $complete) {
                $errors['goals.'.$goal->id.'.rating'] = __('performance.validation.rating_required', ['item' => $goal->title]);
            }

            $goal->forceFill([$ratingColumn => $rating, $commentColumn => $input['comment'] ?? null])->save();
        }

        foreach ($review->competencies()->with('competency')->get() as $row) {
            /** @var PerformanceReviewCompetency $row */
            $input = $competenciesInput[$row->id] ?? [];
            $rating = $this->rating($input['rating'] ?? null, $allowed);

            if ($rating === false) {
                $errors['competencies.'.$row->id.'.rating'] = __('performance.validation.rating_invalid');

                continue;
            }

            if ($rating === null && $complete) {
                $errors['competencies.'.$row->id.'.rating'] = __('performance.validation.rating_required', ['item' => $row->competency->name]);
            }

            $row->forceFill([$ratingColumn => $rating, $commentColumn => $input['comment'] ?? null])->save();
        }

        if ($complete && $goals->isEmpty() && $review->competencies()->doesntExist()) {
            $errors['review'] = __('performance.validation.nothing_to_rate');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Normalised rating, null when empty, false when it is not a level of the scale.
     *
     * @param  list<string>  $allowed
     */
    private function rating(mixed $value, array $allowed): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $value = bcadd((string) $value, '0', 2);

        return in_array($value, $allowed, true) ? $value : false;
    }

    /**
     * @throws ValidationException
     */
    private function checkGoalWeights(PerformanceReview $review): void
    {
        $goals = $review->goalsQuery()->counted()->get();

        if ($goals->isEmpty()) {
            return;
        }

        $total = $this->scoring->totalWeight($goals->pluck('weight'));

        if (bccomp($total, '100', 2) !== 0) {
            throw ValidationException::withMessages(['goals' => __('performance.validation.goal_weights', ['total' => $total])]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureStatus(PerformanceReview $review, PerformanceReviewStatus $status): void
    {
        if ($review->status !== $status) {
            throw ValidationException::withMessages(['review' => __('performance.validation.wrong_status', ['status' => $review->status->label()])]);
        }
    }

    private function notifyReviewer(PerformanceReview $review, string $kind, ?string $reason = null): void
    {
        if ($review->reviewer_id === null) {
            return;
        }

        foreach (User::query()->admins()->where('employee_id', $review->reviewer_id)->get() as $user) {
            $user->notify(new PerformanceReviewNotification($review, $kind, $reason));
        }
    }

    private function notifyApprovers(PerformanceReview $review): void
    {
        $approval = $review->latestApprovalRequest()->first();

        if ($approval === null) {
            return;
        }

        foreach ($this->recipients->for($approval)->reject(fn (User $user): bool => $user->employee_id === $review->employee_id) as $approver) {
            $approver->notify(new PerformanceReviewNotification($review, 'awaiting_approval'));
        }
    }
}
