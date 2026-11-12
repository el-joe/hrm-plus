<?php

namespace App\Services\Notifications\Automation;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Idempotency of scheduled automations: a key ("birthday:2026:12") is claimed once; the callback runs
 * only for the first claim, however often the scheduler (or a manual run) repeats.
 */
class NotificationDispatches
{
    /**
     * @param  callable(): int  $send  returns the number of recipients
     */
    public function once(string $key, string $event, callable $send): bool
    {
        try {
            $id = DB::table('notification_dispatches')->insertGetId(['key' => $key, 'event' => $event, 'recipients' => 0, 'created_at' => now()]);
        } catch (QueryException) {
            return false;
        }

        $count = $send();
        DB::table('notification_dispatches')->where('id', $id)->update(['recipients' => $count]);

        return true;
    }
}
