<?php

namespace App\Enums;

enum OnboardingStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('onboarding.enums.onboarding_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
