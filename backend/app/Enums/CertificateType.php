<?php

namespace App\Enums;

/**
 * Kind of certificate record kept for an employee.
 */
enum CertificateType: string
{
    case Certificate = 'certificate';
    case Licence = 'licence';
    case Qualification = 'qualification';

    public function label(): string
    {
        return __('training.enums.certificate_types.'.$this->value);
    }
}
