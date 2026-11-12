<?php

namespace App\Enums;

/**
 * Status of an announcement. Only draft, published and archived are stored; scheduled (published with a
 * future publish_at) and expired (published, past expires_at) are derived — see Announcement::state().
 */
enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public static function stored(): array
    {
        return [self::Draft, self::Published, self::Archived];
    }

    public function label(): string
    {
        return __('announcements.enums.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Scheduled => 'info',
            self::Published => 'success',
            self::Expired => 'warning',
            self::Archived => 'neutral',
        };
    }
}
