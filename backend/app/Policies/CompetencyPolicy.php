<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Performance configuration (cycles, rating scales, competencies) is managed with
 * performance.manage only; managers holding performance.view work on reviews and goals.
 */
class CompetencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('performance.manage');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('performance.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('performance.manage');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('performance.manage');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('performance.manage');
    }
}
