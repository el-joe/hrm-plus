<?php

namespace App\Policies;

use App\Models\TrainingBudget;
use App\Models\User;

/**
 * Training budgets: read with training.view, maintained with training.manage.
 */
class TrainingBudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function create(User $user): bool
    {
        return $user->can('training.manage');
    }

    public function update(User $user, TrainingBudget $budget): bool
    {
        return $user->can('training.manage');
    }

    public function delete(User $user, TrainingBudget $budget): bool
    {
        return $user->can('training.manage');
    }
}
