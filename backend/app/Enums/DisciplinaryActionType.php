<?php

namespace App\Enums;

/**
 * Outcome of a disciplinary case. Warnings have an escalation level (verbal 1,
 * written 2, final 3); a termination is a recommendation only (HR opens the
 * offboarding case separately).
 */
enum DisciplinaryActionType: string
{
    case VerbalWarning = 'verbal_warning';
    case WrittenWarning = 'written_warning';
    case FinalWarning = 'final_warning';
    case Suspension = 'suspension';
    case SalaryDeduction = 'salary_deduction';
    case Termination = 'termination';

    public function label(): string
    {
        return __('disciplinary.enums.action_types.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::VerbalWarning => 'info',
            self::WrittenWarning, self::Suspension, self::SalaryDeduction => 'warning',
            self::FinalWarning, self::Termination => 'danger',
        };
    }

    public function warningLevel(): ?int
    {
        return match ($this) {
            self::VerbalWarning => 1,
            self::WrittenWarning => 2,
            self::FinalWarning => 3,
            default => null,
        };
    }

    public function isWarning(): bool
    {
        return $this->warningLevel() !== null;
    }

    public static function forLevel(int $level): self
    {
        return match (max(1, min(3, $level))) {
            1 => self::VerbalWarning,
            2 => self::WrittenWarning,
            default => self::FinalWarning,
        };
    }
}
