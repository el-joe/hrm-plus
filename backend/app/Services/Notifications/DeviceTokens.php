<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * FCM registration tokens of the mobile app. A token is unique: registering it again refreshes it, and
 * registering it for another account (someone else signed in on the device) moves it. Tokens tied to a
 * Sanctum token are removed when that token logs out.
 */
class DeviceTokens
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{token: string, platform: string, device_name?: ?string, app_version?: ?string, locale?: ?string}  $data
     */
    public function register(User $user, array $data, ?int $accessTokenId = null): DeviceToken
    {
        return DB::transaction(function () use ($user, $data, $accessTokenId): DeviceToken {
            $device = DeviceToken::query()->lockForUpdate()->firstOrNew(['token_hash' => DeviceToken::hashOf($data['token'])]);
            $isNew = ! $device->exists || $device->user_id !== $user->id;

            $device->fill([
                'user_id' => $user->id,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? $device->device_name,
                'app_version' => $data['app_version'] ?? $device->app_version,
                'locale' => $data['locale'] ?? $device->locale,
                'personal_access_token_id' => $accessTokenId,
                'last_seen_at' => now(),
            ])->save();

            if ($isNew) {
                $this->audit->log('device_token.registered', $device, meta: ['platform' => $device->platform, 'device' => $device->device_name], actor: $user);
            }

            return $device;
        });
    }

    public function unregister(User $user, string $token): bool
    {
        return DeviceToken::query()->where('user_id', $user->id)->where('token_hash', DeviceToken::hashOf($token))->delete() > 0;
    }

    public function remove(DeviceToken $device, User $actor): void
    {
        $device->delete();
        $this->audit->log('device_token.removed', $device, meta: ['platform' => $device->platform], actor: $actor);
    }

    public function forgetAccessToken(int $accessTokenId): void
    {
        DeviceToken::query()->where('personal_access_token_id', $accessTokenId)->delete();
    }

    public function moveAccessToken(int $from, int $to): void
    {
        DeviceToken::query()->where('personal_access_token_id', $from)->update(['personal_access_token_id' => $to]);
    }
}
