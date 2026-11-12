<?php

namespace App\Listeners;

use App\Services\Notifications\DeliveryLog;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Writes the e-mail / push delivery log of centre notifications (App\Services\Notifications\DeliveryLog).
 */
class RecordNotificationDelivery
{
    public function __construct(private readonly DeliveryLog $log) {}

    public function handle(NotificationSending|NotificationSent $event): void
    {
        if ($event instanceof NotificationSending) {
            $this->log->sending($event->notifiable, $event->notification, $event->channel);

            return;
        }

        $this->log->sent($event->notifiable, $event->notification, $event->channel);
    }
}
