<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\LoginHistory;
use App\Models\NotificationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Retention of operational records (system:purge-retention, settings group "retention"): read in-app
 * notifications, the delivery log, login history and device tokens unused for a long time. Payroll,
 * attendance, HR records and the audit log are never touched. Unread notifications are kept.
 */
class RetentionPurge
{
    /**
     * @return array{notifications: int, deliveries: int, login_history: int, device_tokens: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'notifications' => DB::table('notifications')->whereNotNull('read_at')
                ->where('read_at', '<', $now->subDays((int) setting('retention.read_notifications_days')))->delete(),
            'deliveries' => NotificationDelivery::query()->where('created_at', '<', $now->subDays((int) setting('retention.delivery_log_days')))->delete(),
            'login_history' => LoginHistory::query()->where('created_at', '<', $now->subDays((int) setting('retention.login_history_days')))->delete(),
            'device_tokens' => DeviceToken::query()->where(fn ($query) => $query
                ->where('last_seen_at', '<', $now->subDays((int) setting('retention.inactive_device_token_days')))
                ->orWhere(fn ($never) => $never->whereNull('last_seen_at')->where('created_at', '<', $now->subDays((int) setting('retention.inactive_device_token_days')))))
                ->delete(),
        ];
    }
}
