<?php

namespace App\Policies;

use App\Models\User;

class DepartmentPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'departments';
    }

    /**
     * A data-restricted admin would not see a department outside their scope
     * once created, so only unrestricted admins add departments.
     */
    public function create(User $user): bool
    {
        return parent::create($user) && ! $user->hasDataRestrictions();
    }
}
