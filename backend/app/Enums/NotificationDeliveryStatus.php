<?php

namespace App\Enums;

/**
 * Outcome of one channel send in the notification delivery log.
 */
enum NotificationDeliveryStatus: string
{
    case Sending = 'sending';
    case Sent = 'sent';
    // Push through the simulated provider (blocker B5): recorded, not delivered.
    case Simulated = 'simulated';
    case Failed = 'failed';
    // Nothing to deliver to (e.g. push without a registered device).
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('notifications.delivery_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sending => 'info',
            self::Sent => 'success',
            self::Simulated => 'warning',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
        };
    }
}
