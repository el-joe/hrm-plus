<?php

namespace App\Enums;

/**
 * What a pipeline stage means, independent of its (editable) name. The code
 * reacts to categories only: a hired / rejected / withdrawn stage closes the
 * application, "offer" is where a sent offer moves it, "interview" where a
 * scheduled interview moves it.
 */
enum StageCategory: string
{
    case Open = 'open';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return __('recruitment.enums.stage_categories.'.$this->value);
    }

    public function applicationStatus(): ApplicationStatus
    {
        return match ($this) {
            self::Hired => ApplicationStatus::Hired,
            self::Rejected => ApplicationStatus::Rejected,
            self::Withdrawn => ApplicationStatus::Withdrawn,
            default => ApplicationStatus::Active,
        };
    }

    public function isTerminal(): bool
    {
        return $this->applicationStatus() !== ApplicationStatus::Active;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Hired => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
            self::Offer => 'warning',
            self::Interview => 'info',
            self::Open => 'primary',
        };
    }
}
