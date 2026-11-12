<?php

namespace App\Services\Notifications\Push;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Simulated push provider: nothing leaves the server. The message title and the device count are written
 * to the application log and the delivery log shows the send as "Simulated".
 */
class SimulatedPushChannel implements PushChannel
{
    public function name(): string
    {
        return 'simulated';
    }

    public function isSimulated(): bool
    {
        return true;
    }

    public function send(Collection $devices, PushMessage $message): PushResult
    {
        Log::info('Simulated push notification', [
            'title' => $message->title,
            'devices' => $devices->count(),
            'event' => $message->data['event'] ?? null,
        ]);

        return new PushResult(sent: $devices->count(), failed: 0, simulated: true);
    }
}
