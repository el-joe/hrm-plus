<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Translated name for system roles; custom roles show the name as typed.
     */
    public function displayName(): string
    {
        $key = 'permissions.roles.'.$this->name;

        return Lang::has($key) ? __($key) : $this->name;
    }

    public function isSuperAdminRole(): bool
    {
        return $this->name === config('permissions.super_admin_role');
    }
}
