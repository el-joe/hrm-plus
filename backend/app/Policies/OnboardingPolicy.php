<?php

namespace App\Policies;

use App\Models\Onboarding;
use App\Models\User;

/**
 * Onboardings, scoped through the employee. Templates: onboarding.manage.
 */
class OnboardingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('onboarding.view');
    }

    public function view(User $user, Onboarding $onboarding): bool
    {
        return $user->can('onboarding.view') && $onboarding->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('onboarding.create');
    }

    public function update(User $user, Onboarding $onboarding): bool
    {
        return $user->can('onboarding.update') && $onboarding->isVisibleTo($user);
    }

    public function delete(User $user, Onboarding $onboarding): bool
    {
        return $user->can('onboarding.delete') && $onboarding->isVisibleTo($user);
    }
}
