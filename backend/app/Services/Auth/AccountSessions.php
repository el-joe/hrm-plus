<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\DeviceTokens;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Where an account is signed in: web browser sessions (database session driver, rows of the
 * sessions table carrying the user id) and mobile app tokens (Sanctum personal access tokens).
 * Used by the portal "Account settings" page and the API (GET/DELETE /api/v1/auth/sessions).
 *
 * Every query is bound to the given user: an id of somebody else's session / token is simply
 * not found.
 */
class AccountSessions
{
    public function __construct(private readonly DeviceTokens $devices, private readonly AuditLogger $audit) {}

    public static function usesDatabaseSessions(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return list<array{id: string, ip_address: ?string, user_agent: ?string, browser: string, platform: string, last_active_at: Carbon, is_current: bool}>
     */
    public function webSessions(User $user, ?string $currentSessionId = null): array
    {
        if (! self::usesDatabaseSessions()) {
            return [];
        }

        return $this->sessionTable()->where('user_id', $user->id)->orderByDesc('last_activity')->limit(50)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                ...self::describeAgent($row->user_agent),
                'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity),
                'is_current' => $currentSessionId !== null && hash_equals((string) $row->id, $currentSessionId),
            ])->values()->all();
    }

    public function revokeWebSession(User $user, string $sessionId): bool
    {
        if (! self::usesDatabaseSessions()) {
            return false;
        }

        $deleted = $this->sessionTable()->where('user_id', $user->id)->where('id', $sessionId)->delete() > 0;

        if ($deleted) {
            $this->audit->log('auth.session_revoked', $user, meta: ['kind' => 'web'], actor: $user);
        }

        return $deleted;
    }

    /**
     * Ends every web session of the account except the given one.
     */
    public function revokeOtherWebSessions(User $user, ?string $exceptSessionId = null): int
    {
        if (! self::usesDatabaseSessions()) {
            return 0;
        }

        return $this->sessionTable()->where('user_id', $user->id)
            ->when($exceptSessionId !== null, fn (Builder $query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    /**
     * @return list<array{id: int, name: string, created_at: ?Carbon, last_used_at: ?Carbon, expires_at: ?Carbon, is_current: bool}>
     */
    public function tokens(User $user, ?int $currentTokenId = null): array
    {
        return $user->tokens()->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')->orderByDesc('id')->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => (int) $token->id,
                'name' => (string) $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'is_current' => $currentTokenId !== null && (int) $token->id === $currentTokenId,
            ])->values()->all();
    }

    /**
     * Revokes one of the account's app tokens and the push registrations made with it.
     */
    public function revokeToken(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            return false;
        }

        $this->devices->forgetAccessToken((int) $token->id);
        $token->delete();
        $this->audit->log('auth.session_revoked', $user, meta: ['kind' => 'token', 'device' => $token->name], actor: $user);

        return true;
    }

    /**
     * Revokes every app token of the account except the given one.
     */
    public function revokeOtherTokens(User $user, ?int $exceptTokenId = null): int
    {
        $tokens = $user->tokens()->when($exceptTokenId !== null, fn ($query) => $query->whereKeyNot($exceptTokenId))->pluck('id');

        foreach ($tokens as $id) {
            $this->devices->forgetAccessToken((int) $id);
        }

        return $user->tokens()->whereIn('id', $tokens)->delete();
    }

    /**
     * A short, human description of a browser user agent (no external parser).
     *
     * @return array{browser: string, platform: string}
     */
    public static function describeAgent(?string $agent): array
    {
        $agent = (string) $agent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            str_contains($agent, 'Dart/') => 'App',
            default => '—',
        };

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '—',
        };

        return ['browser' => $browser, 'platform' => $platform];
    }

    private function sessionTable(): Builder
    {
        return DB::connection(config('session.connection'))->table((string) config('session.table', 'sessions'));
    }
}
