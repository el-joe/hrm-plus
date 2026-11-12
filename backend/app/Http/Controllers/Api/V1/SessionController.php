<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountSessions;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Devices signed in to the app with this account (Sanctum tokens), and revoking one of them.
 */
class SessionController extends Controller
{
    public function __construct(private readonly AccountSessions $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $current = (int) $request->user()->currentAccessToken()->id;

        return ApiResponse::success(array_map(fn (array $token): array => [
            'id' => $token['id'],
            'name' => $token['name'],
            'created_at' => $token['created_at']?->toIso8601String(),
            'last_used_at' => $token['last_used_at']?->toIso8601String(),
            'expires_at' => $token['expires_at']?->toIso8601String(),
            'is_current' => $token['is_current'],
        ], $this->sessions->tokens($request->user(), $current)));
    }

    /**
     * Revoke one app token of this account (404 when it is not one of yours).
     */
    public function destroy(Request $request, int $session): JsonResponse
    {
        abort_unless($this->sessions->revokeToken($request->user(), $session), 404);

        return ApiResponse::success(message: __('app.account.session_revoked'));
    }
}
