<?php

namespace App\Policies;

use App\Models\TrainingCertificate;
use App\Models\User;

/**
 * Certificates in the admin area (inside the data scope): viewed and downloaded with
 * training.view, recorded / edited / deleted with training.update.
 */
class TrainingCertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function view(User $user, TrainingCertificate $certificate): bool
    {
        return $user->can('training.view') && $certificate->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('training.update');
    }

    public function update(User $user, TrainingCertificate $certificate): bool
    {
        return $user->can('training.update') && $certificate->isVisibleTo($user);
    }

    public function delete(User $user, TrainingCertificate $certificate): bool
    {
        return $this->update($user, $certificate);
    }

    public function export(User $user): bool
    {
        return $user->can('training.export');
    }
}
