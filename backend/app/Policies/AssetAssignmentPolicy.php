<?php

namespace App\Policies;

use App\Models\AssetAssignment;
use App\Models\User;

/**
 * Custody records, scoped through the employee: assets.view lists them;
 * assets.update records returns, losses and paper acknowledgements of
 * active assignments. Nobody records their own return or acknowledgement
 * from the admin area (employees acknowledge in the portal / app).
 */
class AssetAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.view');
    }

    public function view(User $user, AssetAssignment $assignment): bool
    {
        return $user->can('assets.view') && $assignment->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('assets.update');
    }

    public function update(User $user, AssetAssignment $assignment): bool
    {
        return $user->can('assets.update') && $assignment->isActive() && $assignment->isVisibleTo($user)
            && $user->employee_id !== $assignment->employee_id;
    }

    public function export(User $user): bool
    {
        return $user->can('assets.export');
    }
}
