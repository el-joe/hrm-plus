<?php

namespace App\Enums;

enum OffboardingStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('onboarding.enums.offboarding_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
