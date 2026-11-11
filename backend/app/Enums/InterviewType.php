<?php

namespace App\Enums;

/**
 * Kind of interview round.
 */
enum InterviewType: string
{
    case Screening = 'screening';
    case Technical = 'technical';
    case Hr = 'hr';
    case Final = 'final';
    case Other = 'other';

    public function label(): string
    {
        return __('recruitment.enums.interview_types.'.$this->value);
    }
}
