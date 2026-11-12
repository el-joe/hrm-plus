<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\User;

/**
 * Training sessions are company catalogue records: listed with training.view,
 * scheduled / edited / completed / cancelled with training.manage. Nominating
 * participants needs training.create (TrainingEnrolmentPolicy), attendance and
 * outcomes training.update.
 */
class TrainingSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function view(User $user, TrainingSession $session): bool
    {
        return $user->can('training.view');
    }

    public function create(User $user): bool
    {
        return $user->can('training.manage');
    }

    public function update(User $user, TrainingSession $session): bool
    {
        return $user->can('training.manage');
    }

    public function nominate(User $user, TrainingSession $session): bool
    {
        return $user->can('training.create') && $session->acceptsEnrolments();
    }

    public function recordAttendance(User $user, TrainingSession $session): bool
    {
        return $user->can('training.update') && $session->start_date->lte(today()) && $session->status->value !== 'cancelled';
    }
}
