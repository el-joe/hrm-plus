<?php

namespace App\Enums;

/**
 * Where a candidate / application came from.
 */
enum CandidateSource: string
{
    case CareerPage = 'career_page';
    case Referral = 'referral';
    case LinkedIn = 'linkedin';
    case JobBoard = 'job_board';
    case Agency = 'agency';
    case WalkIn = 'walk_in';
    case Internal = 'internal';
    case Other = 'other';

    public function label(): string
    {
        return __('recruitment.enums.sources.'.$this->value);
    }
}
