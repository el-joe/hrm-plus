<?php

namespace App\Enums;

/**
 * An interviewer's overall recommendation on a scorecard.
 */
enum ScorecardRecommendation: string
{
    case StrongYes = 'strong_yes';
    case Yes = 'yes';
    case No = 'no';
    case StrongNo = 'strong_no';

    public function label(): string
    {
        return __('recruitment.enums.recommendations.'.$this->value);
    }
}
