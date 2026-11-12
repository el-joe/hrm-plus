<?php

namespace App\Policies;

use App\Models\AssetCategory;
use App\Models\User;

/**
 * Asset categories are configuration: assets.manage for everything; anyone
 * who may view assets sees them in filters and forms.
 */
class AssetCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('assets.manage');
    }

    public function update(User $user, AssetCategory $category): bool
    {
        return $user->can('assets.manage');
    }

    public function delete(User $user, AssetCategory $category): bool
    {
        return $user->can('assets.manage');
    }
}
