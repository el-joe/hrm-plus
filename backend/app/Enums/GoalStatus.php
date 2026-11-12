<?php

namespace App\Enums;

/**
 * Cancelled goals keep their history but are left out of weights and scores.
 */
enum GoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('performance.enums.goal_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Counted in weights and scores.
     */
    public function counts(): bool
    {
        return $this !== self::Cancelled;
    }
}
