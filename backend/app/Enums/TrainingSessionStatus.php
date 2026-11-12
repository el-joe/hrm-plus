<?php

namespace App\Enums;

/**
 * Life of a training session. Only scheduled sessions take enrolments.
 */
enum TrainingSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('training.enums.session_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
