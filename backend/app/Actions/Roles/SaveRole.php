<?php

namespace App\Actions\Roles;

use App\Models\Role;
use App\Services\Authorization\AdminAccessManager;
use Illuminate\Support\Facades\DB;

/**
 * Creates a custom role or updates an existing one together with its
 * permission set. System roles keep their name.
 */
class SaveRole
{
    public function __construct(private readonly AdminAccessManager $access) {}

    /**
     * @param  array{name: string, description?: string|null, permissions?: list<string>}  $data
     */
    public function handle(array $data, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($data, $role): Role {
            $role ??= new Role(['guard_name' => config('permissions.guard'), 'is_system' => false]);

            if (! $role->is_system) {
                $role->name = $data['name'];
            }

            $role->description = $data['description'] ?? null;
            $role->save();

            $this->access->syncRolePermissions($role, $data['permissions'] ?? []);

            return $role;
        });
    }
}
