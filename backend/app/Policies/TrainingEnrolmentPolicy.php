<?php

namespace App\Policies;

use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Enrolments in the admin area (inside the data scope): listed with training.view;
 * created (nominations) with training.create; decided by whoever the approval engine
 * lets act on the current step (never their own); outcomes with training.update;
 * cancelled by training.manage or by the admin who nominated.
 */
class TrainingEnrolmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function view(User $user, TrainingEnrolment $enrolment): bool
    {
        return $user->can('training.view') && $enrolment->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('training.create');
    }

    public function export(User $user): bool
    {
        return $user->can('training.export');
    }

    public function decide(User $user, TrainingEnrolment $enrolment): bool
    {
        $approval = $enrolment->latestApprovalRequest;

        return $enrolment->isPending() && $user->employee_id !== $enrolment->employee_id
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function update(User $user, TrainingEnrolment $enrolment): bool
    {
        return $user->can('training.update') && $enrolment->isVisibleTo($user);
    }

    public function cancel(User $user, TrainingEnrolment $enrolment): bool
    {
        return ($user->can('training.manage') || $enrolment->requested_by === $user->id) && $enrolment->isVisibleTo($user)
            && ($enrolment->isPending() || ($enrolment->isEnrolled() && $enrolment->session->start_date->gt(today())));
    }
}
