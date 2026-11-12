<?php

namespace App\Enums;

/**
 * A qualitative goal, or a KPI measured against a numeric target.
 */
enum GoalType: string
{
    case Goal = 'goal';
    case Kpi = 'kpi';

    public function label(): string
    {
        return __('performance.enums.goal_types.'.$this->value);
    }
}
