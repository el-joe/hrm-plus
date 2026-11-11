<?php

namespace App\Enums;

/**
 * Status of an onboarding task or an offboarding handover item. "skipped"
 * (not applicable / waived) counts as finished for progress and completion.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('onboarding.enums.task_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Done => 'success',
            self::Skipped => 'neutral',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::Pending;
    }
}
