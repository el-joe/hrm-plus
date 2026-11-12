<?php

namespace App\Enums;

/**
 * Who put the employee on a session: HR (enrolled at once), a manager (approval) or the employee (approval).
 */
enum TrainingEnrolmentSource: string
{
    case Hr = 'hr';
    case Manager = 'manager';
    case Employee = 'self';

    public function label(): string
    {
        return __('training.enums.enrolment_sources.'.$this->value);
    }
}
