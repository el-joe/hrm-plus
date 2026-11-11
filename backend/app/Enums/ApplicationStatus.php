<?php

namespace App\Enums;

/**
 * Status of a job application, derived from the category of its stage.
 */
enum ApplicationStatus: string
{
    case Active = 'active';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return __('recruitment.enums.application_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::Hired => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
        };
    }
}
