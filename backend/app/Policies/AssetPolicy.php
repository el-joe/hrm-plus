<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

/**
 * The asset register inside the admin's data scope (asset branch):
 * assets.view lists and opens; assets.create registers; assets.update edits,
 * assigns, takes returns and changes status (maintenance, damaged, lost);
 * assets.delete retires; assets.export downloads the register.
 */
class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('assets.view') && $asset->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('assets.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('assets.update') && $asset->isVisibleTo($user);
    }

    public function assign(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset) && $asset->isAvailable();
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('assets.delete') && $asset->isVisibleTo($user);
    }

    public function export(User $user): bool
    {
        return $user->can('assets.export');
    }
}
