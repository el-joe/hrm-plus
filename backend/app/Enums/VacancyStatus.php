<?php

namespace App\Enums;

/**
 * Lifecycle of a vacancy. Only "open" vacancies take applications (and are
 * listed on the career page when public and inside the publish window).
 */
enum VacancyStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case OnHold = 'on_hold';
    case Filled = 'filled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('recruitment.enums.vacancy_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::OnHold => 'warning',
            self::Filled => 'primary',
            self::Closed, self::Cancelled => 'neutral',
            self::Draft => 'info',
        };
    }

    public function acceptsApplications(): bool
    {
        return $this === self::Open;
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Filled, self::Closed, self::Cancelled], true);
    }
}
