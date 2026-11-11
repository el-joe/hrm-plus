<?php

namespace App\Enums;

/**
 * Kind of offboarding handover item.
 */
enum HandoverCategory: string
{
    case Asset = 'asset';
    case Document = 'document';
    case Access = 'access';
    case Finance = 'finance';
    case Other = 'other';

    public function label(): string
    {
        return __('onboarding.enums.handover_categories.'.$this->value);
    }
}
