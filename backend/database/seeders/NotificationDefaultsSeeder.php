<?php

namespace Database\Seeders;

use App\Services\Notifications\NotificationTemplates;
use Illuminate\Database\Seeder;

/**
 * Notification templates (Phase 8b, every environment): one row per event, channel and language, copied
 * from the current lang texts of config/notification_events.php. Existing rows (possibly edited by an
 * administrator) are never overwritten, so it is safe to re-run after a release adds events.
 */
class NotificationDefaultsSeeder extends Seeder
{
    public function run(NotificationTemplates $templates): void
    {
        $templates->seedDefaults();
    }
}
