<?php

use App\Actions\Announcements\ManageAnnouncement;
use App\Models\Announcement;
use App\Models\User;

/*
| Helpers of the announcement tests (Phase 8b). Required by each announcement test file;
| guarded so several files can include them in one run.
*/

if (! function_exists('announcementInput')) {
    /**
     * Valid input of the announcement form / ManageAnnouncement::save().
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function announcementInput(array $overrides = []): array
    {
        return [
            'title_ar' => 'إعلان تجريبي',
            'title_en' => 'Test announcement',
            'body_ar' => '<p>نص الإعلان</p>',
            'body_en' => '<p>Announcement text</p>',
            'type' => 'general',
            'audience' => Announcement::AUDIENCE_ALL,
            'notify_email' => false,
            'notify_push' => false,
            ...$overrides,
        ];
    }

    /**
     * Create (and by default publish) an announcement through the action.
     *
     * @param  array<string, mixed>  $overrides
     */
    function makeAnnouncement(User $author, array $overrides = [], bool $publish = true): Announcement
    {
        return app(ManageAnnouncement::class)->save(announcementInput($overrides), $author, publish: $publish);
    }
}
