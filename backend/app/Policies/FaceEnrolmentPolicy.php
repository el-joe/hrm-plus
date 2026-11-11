<?php

namespace App\Policies;

use App\Models\FaceEnrolment;
use App\Models\User;

/**
 * The face enrolment register (no biometric content is ever shown).
 */
class FaceEnrolmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('face_verification.view');
    }

    public function delete(User $user, FaceEnrolment $enrolment): bool
    {
        return $user->can('face_verification.delete') && $enrolment->isActive() && $enrolment->isVisibleTo($user);
    }
}
