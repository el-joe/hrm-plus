<?php

namespace App\Enums;

/**
 * Where an interview takes place.
 */
enum InterviewMode: string
{
    case InPerson = 'in_person';
    case Online = 'online';
    case Phone = 'phone';

    public function label(): string
    {
        return __('recruitment.enums.interview_modes.'.$this->value);
    }
}
