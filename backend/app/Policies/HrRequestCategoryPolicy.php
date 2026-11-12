<?php

namespace App\Policies;

use App\Models\HrRequestCategory;
use App\Models\User;

/**
 * Request categories are configuration: hr_requests.manage. A category already used by requests is
 * deactivated instead of deleted.
 */
class HrRequestCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr_requests.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('hr_requests.manage');
    }

    public function update(User $user, HrRequestCategory $category): bool
    {
        return $user->can('hr_requests.manage');
    }

    public function delete(User $user, HrRequestCategory $category): bool
    {
        return $user->can('hr_requests.manage') && $category->requests()->withoutGlobalScopes()->doesntExist();
    }
}
