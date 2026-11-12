<?php

namespace App\Enums;

/**
 * Kind of an announcement (spec module 14: company announcements, important notices, policy updates).
 */
enum AnnouncementType: string
{
    case General = 'general';
    case Important = 'important';
    case PolicyUpdate = 'policy_update';
    case Event = 'event';

    public function label(?string $locale = null): string
    {
        return __('announcements.enums.types.'.$this->value, [], $locale);
    }

    public function tone(): string
    {
        return match ($this) {
            self::General => 'neutral',
            self::Important => 'danger',
            self::PolicyUpdate => 'info',
            self::Event => 'success',
        };
    }
}
