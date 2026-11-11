<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return __('recruitment.enums.interview_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
            self::NoShow => 'danger',
        };
    }
}
