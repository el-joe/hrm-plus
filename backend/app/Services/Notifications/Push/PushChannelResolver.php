<?php

namespace App\Services\Notifications\Push;

use RuntimeException;

/**
 * The push provider configured in settings (push.provider). FCM without a usable service-account key
 * falls back to the simulated provider rather than failing every notification.
 */
class PushChannelResolver
{
    public function current(): PushChannel
    {
        if (setting('push.provider') !== 'fcm') {
            return new SimulatedPushChannel;
        }

        $json = setting('push.fcm_service_account');

        try {
            $credentials = FcmHttpV1PushChannel::parseCredentials((string) $json);
        } catch (RuntimeException) {
            return new SimulatedPushChannel;
        }

        return new FcmHttpV1PushChannel($credentials, (string) setting('push.android_channel_id'), (int) setting('push.ttl_hours') * 3600);
    }

    /**
     * Whether pushes are actually delivered (FCM configured with valid credentials).
     */
    public function isLive(): bool
    {
        return ! $this->current()->isSimulated();
    }
}
