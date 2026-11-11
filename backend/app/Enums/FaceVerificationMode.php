<?php

namespace App\Enums;

/**
 * Face verification on mobile attendance, per attendance policy.
 */
enum FaceVerificationMode: string
{
    case Off = 'off';
    case Optional = 'optional';
    case Required = 'required';

    public function label(): string
    {
        return __('face.enums.modes.'.$this->value);
    }
}
