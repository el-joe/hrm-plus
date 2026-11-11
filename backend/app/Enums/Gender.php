<?php

namespace App\Enums;

/**
 * Gender, recorded only where company policy requires it (nullable).
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return __('employees.enums.genders.'.$this->value);
    }
}
