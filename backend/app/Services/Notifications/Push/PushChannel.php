<?php

namespace App\Services\Notifications\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Collection;

/**
 * A push provider (blocker B5: the Firebase project is not supplied yet).
 *
 * - FcmHttpV1PushChannel: Firebase Cloud Messaging HTTP v1 with a service-account key (setting
 *   push.fcm_service_account, uploaded on the push settings screen) — wired, delivery untested;
 * - SimulatedPushChannel: records the message in the log only (labelled "Simulated" everywhere).
 *
 * The active one is chosen by setting push.provider (PushChannelResolver).
 */
interface PushChannel
{
    public function name(): string;

    public function isSimulated(): bool;

    /**
     * @param  Collection<int, DeviceToken>  $devices
     */
    public function send(Collection $devices, PushMessage $message): PushResult;
}
