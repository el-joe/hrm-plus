<?php

namespace App\Models;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One channel send of a notification (delivery log). Written by App\Services\Notifications\DeliveryLog;
 * holds the recipient address and the outcome, never the message text.
 */
#[Fillable([
    'notification_id', 'user_id', 'event', 'notification_type', 'channel', 'status', 'attempts', 'recipient',
    'provider', 'provider_message_id', 'error', 'sent_at', 'failed_at',
])]
class NotificationDelivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotificationDeliveryStatus::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
