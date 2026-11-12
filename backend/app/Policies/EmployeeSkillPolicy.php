<?php

namespace App\Policies;

use App\Models\User;

/**
 * Skills matrix: read with training.view, levels set with training.update (the
 * employee must also be visible — checked by the controller through the scoped query).
 */
class EmployeeSkillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function create(User $user): bool
    {
        return $user->can('training.update');
    }
}
