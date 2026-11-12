<?php

namespace App\Enums;

/**
 * Where an employee's skill level came from.
 */
enum SkillSource: string
{
    case Manual = 'manual';
    case Training = 'training';

    public function label(): string
    {
        return __('training.enums.skill_sources.'.$this->value);
    }
}
