<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function view(User $user, JobApplication $application): bool
    {
        return $user->can('recruitment.view') && $application->isVisibleTo($user);
    }

    /**
     * Move between stages, notes.
     */
    public function update(User $user, JobApplication $application): bool
    {
        return $user->can('recruitment.update') && $application->isVisibleTo($user);
    }

    /**
     * Hiring needs both recruitment and employee creation rights.
     */
    public function convert(User $user, JobApplication $application): bool
    {
        return $user->can('recruitment.update') && $user->can('employees.create') && $application->isVisibleTo($user);
    }
}
