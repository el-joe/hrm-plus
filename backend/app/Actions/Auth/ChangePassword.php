<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AccountSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sets a new password chosen by the account owner (portal account settings, PUT /api/v1/auth/password)
 * or after a verified reset code. Every other place the account is signed in is ended: other web
 * sessions and app tokens are revoked, the caller's own session / token is kept when given.
 * The current password is checked by the Form Request (current_password rule), not here.
 */
class ChangePassword
{
    public function __construct(private readonly AccountSessions $sessions, private readonly AuditLogger $audit) {}

    public function handle(User $user, string $password, ?string $keepSessionId = null, ?int $keepTokenId = null, string $reason = 'changed'): void
    {
        DB::transaction(function () use ($user, $password, $keepSessionId, $keepTokenId, $reason): void {
            $user->forceFill([
                'password' => $password,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->sessions->revokeOtherTokens($user, $keepTokenId);
            $this->sessions->revokeOtherWebSessions($user, $keepSessionId);

            $this->audit->log($reason === 'reset' ? 'auth.password_reset' : 'auth.password_changed', $user, meta: ['channel' => $keepTokenId !== null || $reason === 'reset' ? 'api' : 'portal'], actor: $user);
        });
    }
}
