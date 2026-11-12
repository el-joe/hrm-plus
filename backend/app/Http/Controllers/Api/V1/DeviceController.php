<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DeviceTokenResource;
use App\Models\DeviceToken;
use App\Services\Notifications\DeviceTokens;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Push devices (FCM registration tokens) of the token owner.
 *
 * GET    /api/v1/me/devices   registered devices (the token itself is never returned)
 * POST   /api/v1/me/devices   {token, platform: android|ios|web, device_name?, app_version?, locale?} — idempotent:
 *                             call after every login and whenever FCM rotates the token; 201 when new, 200 when refreshed
 * DELETE /api/v1/me/devices   {token} — on sign-out (POST auth/logout also removes the devices registered with that token)
 */
class DeviceController extends Controller
{
    public function __construct(private readonly DeviceTokens $devices) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(DeviceTokenResource::collection(
            DeviceToken::query()->where('user_id', $request->user()->id)->latest('last_seen_at')->get(),
        )->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512', 'regex:/^[A-Za-z0-9:_\-.]+$/'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
        ]);

        $existed = DeviceToken::query()->where('user_id', $request->user()->id)->where('token_hash', DeviceToken::hashOf($data['token']))->exists();
        $device = $this->devices->register($request->user(), $data, $request->user()->currentAccessToken()?->id);

        return ApiResponse::success(new DeviceTokenResource($device), __('notifications.api.device_registered'), $existed ? 200 : 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        abort_unless($this->devices->unregister($request->user(), $data['token']), 404);

        return ApiResponse::success(message: __('notifications.api.device_removed'));
    }
}
