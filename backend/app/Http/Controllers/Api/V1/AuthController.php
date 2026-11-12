<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\AuthenticateUser;
use App\Enums\AuthArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Notifications\DeviceTokens;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token lifecycle of the employee mobile app.
 *
 * Tokens are Sanctum personal access tokens, one per device, valid for
 * security.api_token_lifetime_minutes minutes (a system setting, also applied to
 * sanctum.expiration at boot). "refresh" rotates: it revokes the
 * presented token and returns a new one with a fresh expiry.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthenticateUser $authenticate): JsonResponse
    {
        $user = $authenticate->handle(
            AuthArea::Api,
            $request->validated('email'),
            $request->validated('password'),
            $request,
        );

        return ApiResponse::success($this->issueToken($user, $request->validated('device_name')), __('api.logged_in'));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $payload = $this->issueToken($user, $current->name);

        // Push devices registered with the old token follow the new one (Phase 8b).
        app(DeviceTokens::class)->moveAccessToken($current->id, (int) $user->tokens()->latest('id')->value('id'));
        $current->delete();

        return ApiResponse::success($payload, __('api.token_refreshed'));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        // The device signed out: it must stop receiving this account's pushes.
        app(DeviceTokens::class)->forgetAccessToken($token->id);
        $token->delete();

        return ApiResponse::success(message: __('api.logged_out'));
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        $request->user()->deviceTokens()->delete();

        return ApiResponse::success(message: __('api.logged_out_all'));
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string, user: array<string, mixed>}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $expiresAt = now()->addMinutes((int) setting('security.api_token_lifetime_minutes'));
        $token = $user->createToken($deviceName, ['employee'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => (new UserResource($user))->resolve(),
        ];
    }
}
