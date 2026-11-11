<?php

namespace App\Services\Auth;

use App\Enums\AuthArea;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes the login history (successes and failures) for every sign-in area.
 */
class LoginRecorder
{
    public function success(User $user, AuthArea $area, Request $request): LoginHistory
    {
        return $this->record($user->email, $area, $request, $user, null);
    }

    public function failure(string $email, AuthArea $area, Request $request, string $reason, ?User $user = null): LoginHistory
    {
        return $this->record($email, $area, $request, $user, $reason);
    }

    private function record(string $email, AuthArea $area, Request $request, ?User $user, ?string $failureReason): LoginHistory
    {
        return LoginHistory::create([
            'user_id' => $user?->getKey(),
            'email' => Str::limit($email, 255, ''),
            'area' => $area,
            'successful' => $failureReason === null,
            'failure_reason' => $failureReason,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '') ?: null,
        ]);
    }
}
