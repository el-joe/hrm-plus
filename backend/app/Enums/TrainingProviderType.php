<?php

namespace App\Enums;

/**
 * Who delivers a course: the company itself or an outside provider.
 */
enum TrainingProviderType: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return __('training.enums.provider_types.'.$this->value);
    }
}
