<?php

namespace App\Policies;

use App\Models\OffboardingCase;
use App\Models\User;

/**
 * Offboarding cases, scoped through the employee. Completing a case
 * terminates the employee, so it needs offboarding.approve AND the right to
 * terminate that employee (employees.manage in scope).
 */
class OffboardingCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('offboarding.view');
    }

    public function view(User $user, OffboardingCase $case): bool
    {
        return $user->can('offboarding.view') && $case->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('offboarding.create');
    }

    public function update(User $user, OffboardingCase $case): bool
    {
        return $user->can('offboarding.update') && $case->isVisibleTo($user);
    }

    public function complete(User $user, OffboardingCase $case): bool
    {
        return $user->can('offboarding.approve') && $user->can('employees.manage') && $case->isVisibleTo($user);
    }

    public function cancel(User $user, OffboardingCase $case): bool
    {
        return $user->can('offboarding.delete') && $case->isVisibleTo($user);
    }
}
