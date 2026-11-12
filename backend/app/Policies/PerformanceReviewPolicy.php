<?php

namespace App\Policies;

use App\Enums\PerformanceReviewStatus;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Performance\PerformanceVisibility;

/**
 * Performance reviews in the admin area. performance.view inside the data scope,
 * limited to the reporting line without performance.manage (PerformanceVisibility).
 * Nobody sees, assesses or decides their own review here (that is the portal).
 *
 * - assess: the manager assessment — the review's reviewer (admin linked to the
 *   reviewer employee) with performance.update, or a performance.manage holder;
 * - decide: HR calibration, whoever the approval engine lets act on the current step;
 * - skipSelf / cancel: performance.manage.
 */
class PerformanceReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('performance.view');
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        return $user->can('performance.view') && app(PerformanceVisibility::class)->canSeeReview($user, $review);
    }

    public function export(User $user): bool
    {
        return $user->can('performance.export');
    }

    public function assess(User $user, PerformanceReview $review): bool
    {
        if ($review->status !== PerformanceReviewStatus::ManagerAssessment || ! $this->view($user, $review)) {
            return false;
        }

        $isReviewer = $user->employee_id !== null && $review->reviewer_id === $user->employee_id;

        return ($isReviewer && $user->can('performance.update')) || $user->can('performance.manage');
    }

    public function decide(User $user, PerformanceReview $review): bool
    {
        $approval = $review->latestApprovalRequest;

        return $review->status === PerformanceReviewStatus::PendingApproval
            && $user->employee_id !== $review->employee_id
            && $approval !== null && $approval->isPending()
            && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function skipSelf(User $user, PerformanceReview $review): bool
    {
        return $review->status === PerformanceReviewStatus::SelfAssessment && $user->can('performance.manage') && $this->view($user, $review);
    }

    public function cancel(User $user, PerformanceReview $review): bool
    {
        return $review->status->isOpen() && $user->can('performance.manage') && $this->view($user, $review);
    }
}
