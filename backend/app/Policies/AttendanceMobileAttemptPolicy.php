<?php

namespace App\Policies;

use App\Models\AttendanceMobileAttempt;
use App\Models\User;

/**
 * Mobile attempts and the suspicious-attempt review queue, within the data
 * scope. Nobody decides on their own attempt.
 */
class AttendanceMobileAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceMobileAttempt $attempt): bool
    {
        return $user->can('attendance.view') && $attempt->isVisibleTo($user);
    }

    public function approve(User $user, AttendanceMobileAttempt $attempt): bool
    {
        return $this->decide($user, $attempt, 'attendance.approve');
    }

    public function reject(User $user, AttendanceMobileAttempt $attempt): bool
    {
        return $this->decide($user, $attempt, 'attendance.reject');
    }

    private function decide(User $user, AttendanceMobileAttempt $attempt, string $permission): bool
    {
        return $user->can($permission) && $attempt->isPendingReview() && $attempt->isVisibleTo($user)
            && $user->employee_id !== $attempt->employee_id;
    }
}
