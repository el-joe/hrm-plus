<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Training catalogue records (providers, programmes, courses, skills): read with
 * training.view, maintained with training.manage. Records in use are deactivated,
 * never deleted.
 */
abstract class TrainingCataloguePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('training.view');
    }

    public function create(User $user): bool
    {
        return $user->can('training.manage');
    }

    /**
     * Also covers activating / deactivating.
     */
    public function update(User $user, Model $record): bool
    {
        return $user->can('training.manage');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('training.manage') && method_exists($record, 'isInUse') && ! $record->isInUse();
    }
}
