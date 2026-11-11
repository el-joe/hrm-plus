<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;

/**
 * Candidates: a recruitment permission AND (for restricted admins) an
 * application to a vacancy inside their data scope.
 */
class CandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.view');
    }

    public function view(User $user, Candidate $candidate): bool
    {
        return $user->can('recruitment.view') && $candidate->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('recruitment.create');
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $user->can('recruitment.update') && $candidate->isVisibleTo($user);
    }

    /**
     * Hired candidates are part of an employee's lineage and are never deleted.
     */
    public function delete(User $user, Candidate $candidate): bool
    {
        return $user->can('recruitment.delete') && $candidate->isVisibleTo($user) && ! $candidate->isHired();
    }
}
