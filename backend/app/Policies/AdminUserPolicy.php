<?php

namespace App\Policies;

use App\Models\User;

/**
 * Rules for managing admin accounts. Registered for the User model in
 * AppServiceProvider. Employee accounts are managed elsewhere (Phase 3) and
 * are never reachable through these abilities.
 */
class AdminUserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('admin_users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $target->isAdmin() && $actor->can('admin_users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('admin_users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('admin_users.update') && $this->mayManage($actor, $target);
    }

    /**
     * Nobody deletes their own account, Super Admins included.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->can('admin_users.delete')
            && $this->mayManage($actor, $target)
            && ! $actor->is($target);
    }

    /**
     * Only a Super Admin may change or remove another Super Admin.
     */
    private function mayManage(User $actor, User $target): bool
    {
        return $target->isAdmin() && ($actor->isSuperAdmin() || ! $target->isSuperAdmin());
    }
}
