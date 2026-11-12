<?php

namespace App\Enums;

/**
 * How a course / session is delivered.
 */
enum TrainingDeliveryMode: string
{
    case Classroom = 'classroom';
    case Online = 'online';
    case Blended = 'blended';

    public function label(): string
    {
        return __('training.enums.delivery_modes.'.$this->value);
    }
}
