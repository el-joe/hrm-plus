<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;

/**
 * Interviews. An interviewer always sees and scores the interviews they sit
 * on (admins need interviews.view; employee accounts use the portal).
 * interviews.manage widens viewing to every interview in the data scope;
 * create / update / delete schedule and change them.
 */
class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('interviews.view') || $user->can('interviews.manage');
    }

    public function view(User $user, Interview $interview): bool
    {
        if ($interview->isInterviewer($user)) {
            return $user->isEmployee() || $user->can('interviews.view') || $user->can('interviews.manage');
        }

        return $user->can('interviews.manage') && $interview->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('interviews.create');
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->can('interviews.update') && $interview->isVisibleTo($user);
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $user->can('interviews.delete') && $interview->isVisibleTo($user);
    }

    public function score(User $user, Interview $interview): bool
    {
        return $interview->isInterviewer($user) && $this->view($user, $interview);
    }

    /**
     * Other interviewers' scorecards: organisers (interviews.update) and managers.
     */
    public function viewScorecards(User $user, Interview $interview): bool
    {
        return ($user->can('interviews.update') || $user->can('interviews.manage')) && $interview->isVisibleTo($user);
    }
}
