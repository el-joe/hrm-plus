<?php

namespace App\Policies;

use App\Models\User;

/**
 * Branch locations are part of their branch: managing them needs branches.update.
 */
class BranchPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'branches';
    }

    /**
     * A data-restricted admin could never see a branch they create, so only
     * unrestricted admins add branches.
     */
    public function create(User $user): bool
    {
        return parent::create($user) && ! $user->hasDataRestrictions();
    }
}
