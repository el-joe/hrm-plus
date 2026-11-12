<?php

namespace App\Enums;

enum CompetencyCategory: string
{
    case Core = 'core';
    case Leadership = 'leadership';
    case Technical = 'technical';
    case Behavioural = 'behavioural';

    public function label(): string
    {
        return __('performance.enums.competency_categories.'.$this->value);
    }
}
