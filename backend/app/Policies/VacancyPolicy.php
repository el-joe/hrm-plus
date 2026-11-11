<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacancy;

/**
 * Vacancies: a recruitment permission AND the vacancy inside the admin's
 * data scope (branch / department).
 */
class VacancyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.view');
    }

    public function view(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.view') && $vacancy->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('recruitment.create');
    }

    public function update(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.update') && $vacancy->isVisibleTo($user);
    }

    /**
     * Only a vacancy nobody applied to can be deleted; others are closed or cancelled.
     */
    public function delete(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.delete') && $vacancy->isVisibleTo($user) && ! $vacancy->applications()->withoutGlobalScopes()->exists();
    }

    /**
     * Give the vacancy its own pipeline.
     */
    public function managePipeline(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.manage') && $vacancy->isVisibleTo($user);
    }
}
