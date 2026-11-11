<?php

namespace App\Enums;

/**
 * Who a work-location rule applies to.
 */
enum WorkLocationScope: string
{
    case Employee = 'employee';
    case Department = 'department';

    public function label(): string
    {
        return __('mobile.enums.location_scopes.'.$this->value);
    }
}
