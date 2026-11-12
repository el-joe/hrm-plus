<?php

namespace App\Enums;

enum HrRequestSource: string
{
    case Portal = 'portal';
    case Api = 'api';
    case Admin = 'admin';

    public function label(): string
    {
        return __('hr_requests.enums.sources.'.$this->value);
    }
}
