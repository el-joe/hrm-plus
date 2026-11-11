<?php

namespace App\Enums;

/**
 * The organiser's decision after an interview.
 */
enum InterviewDecision: string
{
    case Advance = 'advance';
    case Hold = 'hold';
    case Reject = 'reject';

    public function label(): string
    {
        return __('recruitment.enums.decisions.'.$this->value);
    }
}
