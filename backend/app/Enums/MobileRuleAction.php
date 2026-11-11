<?php

namespace App\Enums;

/**
 * What happens to a mobile attempt that breaks a soft rule: refused, or recorded only after review.
 */
enum MobileRuleAction: string
{
    case Reject = 'reject';
    case Review = 'review';

    public function label(): string
    {
        return __('mobile.enums.rule_actions.'.$this->value);
    }
}
