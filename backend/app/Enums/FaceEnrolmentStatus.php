<?php

namespace App\Enums;

/**
 * State of a face enrolment; deleted rows keep no biometric data.
 */
enum FaceEnrolmentStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';

    public function label(): string
    {
        return __('face.enums.enrolment_statuses.'.$this->value);
    }
}
