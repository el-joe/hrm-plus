<?php

namespace Database\Seeders;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\NotificationTemplates;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/**
 * Notification centre demo data (Phase 8b, not in production), before the HR request / announcement demos:
 *
 * - a few preferences (employee1@ turned e-mail off for late arrivals and on for absences; employee2@ turned
 *   push off for announcements; hr.manager@ turned e-mail off for new HR requests);
 * - one customised template (the English in-app text of "payslip_issued"), so "Customised" / "Reset" show;
 * - demo FCM device tokens for employee1@ (android) and employee2@ (ios) — fake tokens: with the simulated
 *   push provider the sends appear in the delivery log as "Simulated";
 * - the scheduled automations run once (people events, attendance alerts, training reminders, approvals
 *   digest) on the synchronous queue, so bells and the delivery log have real content.
 */
class NotificationDemoSeeder extends Seeder
{
    public function run(NotificationPreferences $preferences, NotificationTemplates $templates): void
    {
        if (DeviceToken::query()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        // Earlier demo seeders fake notifications in this process; Phase 8b demo data goes through the real centre.
        Notification::swap(new ChannelManager(app()));

        $employee1 = User::query()->where('email', 'employee1@hrmplus.test')->first();
        $employee2 = User::query()->where('email', 'employee2@hrmplus.test')->first();
        $hr = User::query()->where('email', 'hr.manager@hrmplus.test')->first();

        if ($employee1 === null || $employee2 === null || $hr === null) {
            return;
        }

        $preferences->save($employee1, ['attendance_late_arrival' => ['mail' => false, 'push' => true], 'attendance_absent' => ['mail' => true]]);
        $preferences->save($employee2, ['announcement_published' => ['push' => false]]);
        $preferences->save($hr, ['hr_request_submitted' => ['mail' => false]]);

        $super = User::query()->where('email', 'super.admin@hrmplus.test')->first() ?? $hr;
        $templates->save('payslip_issued', 'database', 'en', 'Your payslip for {{period}} is ready',
            'Hi {{recipient}}, payslip {{number}} for {{period}} is now available in My payroll.', $super);

        foreach ([[$employee1, 'android', 'Pixel 8', '1.0.0 (12)'], [$employee2, 'ios', 'iPhone 15', '1.0.0 (12)']] as [$user, $platform, $device, $version]) {
            $token = 'demo-'.$platform.'-'.bin2hex(random_bytes(24));
            DeviceToken::query()->create([
                'user_id' => $user->id, 'token' => $token, 'token_hash' => DeviceToken::hashOf($token), 'platform' => $platform,
                'device_name' => $device, 'app_version' => $version, 'locale' => $user->locale ?? 'ar',
                'last_seen_at' => CarbonImmutable::now()->subHours(3),
            ]);
        }

        // Pushes go to the simulated provider (no Firebase project yet), recorded in the delivery log.
        app(SettingsService::class)->set('notifications.push_enabled', true);

        foreach (['notifications:people-events', 'notifications:attendance-alerts', 'notifications:training-reminders', 'notifications:approvals-digest'] as $command) {
            Artisan::call($command);
        }

    }
}
