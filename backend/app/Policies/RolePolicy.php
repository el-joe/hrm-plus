<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    /**
     * The Super Admin role always holds every permission and cannot be edited.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && ! $role->isSuperAdminRole();
    }

    /**
     * System (seeded) roles cannot be deleted; custom roles can.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && ! $role->is_system;
    }
}
