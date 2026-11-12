<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration token of the mobile app (Phase 8b). One row per physical install; registering the
 * same token again (another account signed in on the device) moves it to the new user.
 */
#[Fillable(['user_id', 'token', 'token_hash', 'platform', 'device_name', 'app_version', 'locale', 'personal_access_token_id', 'last_seen_at'])]
#[Hidden(['token', 'token_hash'])]
class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The token shortened for screens and logs.
     */
    public function maskedToken(): string
    {
        return substr($this->token, 0, 8).'…'.substr($this->token, -6);
    }
}
