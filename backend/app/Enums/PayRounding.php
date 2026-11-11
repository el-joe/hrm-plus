<?php

namespace App\Enums;

/**
 * How a component amount is rounded to its precision (bcmath, never float).
 */
enum PayRounding: string
{
    case HalfUp = 'half_up';
    case Down = 'down';
    case Up = 'up';

    public function label(): string
    {
        return __('payroll.enums.roundings.'.$this->value);
    }
}
