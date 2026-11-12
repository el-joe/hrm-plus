<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ViolationType;

/**
 * The violation catalogue is configuration: disciplinary.manage for everything.
 * Types already used by a case cannot be deleted (the controller refuses; deactivate them instead).
 */
class ViolationTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('disciplinary.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('disciplinary.manage');
    }

    public function update(User $user, ViolationType $type): bool
    {
        return $user->can('disciplinary.manage');
    }

    public function delete(User $user, ViolationType $type): bool
    {
        return $user->can('disciplinary.manage');
    }
}
