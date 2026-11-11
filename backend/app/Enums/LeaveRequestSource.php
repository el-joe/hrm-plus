<?php

namespace App\Enums;

/**
 * Where a leave request was raised.
 */
enum LeaveRequestSource: string
{
    case Admin = 'admin';
    case Portal = 'portal';
    case Api = 'api';

    public function label(): string
    {
        return __('leave.enums.sources.'.$this->value);
    }
}
