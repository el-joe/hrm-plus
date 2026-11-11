<?php

namespace App\Enums;

/**
 * Server decision on a mobile GPS check-in / check-out attempt.
 */
enum MobileAttemptOutcome: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Flagged = 'flagged';

    public function label(): string
    {
        return __('mobile.enums.outcomes.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Flagged => 'warning',
        };
    }
}
