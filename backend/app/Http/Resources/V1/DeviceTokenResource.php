<?php

namespace App\Http\Resources\V1;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A registered push device of the token owner. The FCM token itself is never returned.
 *
 * @mixin DeviceToken
 */
class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'locale' => $this->locale,
            'is_current' => $this->personal_access_token_id !== null && $this->personal_access_token_id === $request->user()?->currentAccessToken()?->id,
            'last_seen_at' => $this->last_seen_at?->toIso8601ZuluString(),
            'registered_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
