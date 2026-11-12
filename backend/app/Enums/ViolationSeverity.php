<?php

namespace App\Enums;

/**
 * How serious a violation type is (catalogue of disciplinary violations).
 */
enum ViolationSeverity: string
{
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Major = 'major';
    case Gross = 'gross';

    public function label(): string
    {
        return __('disciplinary.enums.severities.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Minor => 'neutral',
            self::Moderate => 'warning',
            self::Major, self::Gross => 'danger',
        };
    }
}
