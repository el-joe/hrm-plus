<?php

namespace App\Enums;

use App\Models\User;

/**
 * The three places a person can sign in. Each area knows its guard, its
 * routes and which accounts it admits, so login code never branches on strings.
 */
enum AuthArea: string
{
    case Admin = 'admin';
    case Portal = 'portal';
    case Api = 'api';

    /**
     * The auth guard that holds the signed-in user for this area.
     */
    public function guard(): string
    {
        return match ($this) {
            self::Admin => 'admin',
            self::Portal => 'employee',
            self::Api => 'sanctum',
        };
    }

    /**
     * Whether the account is allowed to sign in to this area at all.
     */
    public function admits(User $user): bool
    {
        return match ($this) {
            self::Admin => $user->isAdmin(),
            self::Portal => $user->canAccessPortal(),
            self::Api => $user->isEmployee(),
        };
    }

    public function loginRoute(): string
    {
        return $this->value.'.login';
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            default => 'portal.home',
        };
    }

    /**
     * Directory under resources/views that holds this area's auth views.
     */
    public function viewDirectory(): string
    {
        return match ($this) {
            self::Admin => 'admin',
            default => 'employee',
        };
    }

    public function label(): string
    {
        return __('app.areas.'.$this->value);
    }
}
