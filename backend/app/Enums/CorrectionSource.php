<?php

namespace App\Enums;

/**
 * Who raised an attendance correction.
 */
enum CorrectionSource: string
{
    case Admin = 'admin';
    case Portal = 'portal';
    case Api = 'api';

    public function label(): string
    {
        return __('attendance.enums.correction_sources.'.$this->value);
    }
}
