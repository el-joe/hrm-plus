<?php

namespace App\Services\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Changes what an admin or a role is allowed to do, and audits every change:
 * role assignment, role permissions and data scopes.
 */
class AdminAccessManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Replace the roles of an admin account.
     *
     * @param  list<int>  $roleIds
     */
    public function syncRoles(User $admin, array $roleIds): void
    {
        $before = $admin->roles()->orderBy('name')->pluck('name')->all();

        $admin->syncRoles(Role::query()->whereKey($roleIds)->get());

        $after = $admin->roles()->orderBy('name')->pluck('name')->all();

        if ($before !== $after) {
            $this->audit->log('admin_user.roles_updated', $admin, ['roles' => $before], ['roles' => $after]);
        }
    }

    /**
     * Replace the permissions of a role.
     *
     * @param  list<string>  $permissions  Permission names from the catalogue.
     */
    public function syncRolePermissions(Role $role, array $permissions): void
    {
        $before = $role->permissions()->orderBy('name')->pluck('name')->all();

        $role->syncPermissions($permissions);

        $after = $role->permissions()->orderBy('name')->pluck('name')->all();

        if ($before !== $after) {
            $this->audit->log('role.permissions_updated', $role, ['permissions' => $before], ['permissions' => $after]);
        }
    }

    /**
     * Replace the data scopes of an admin account. An empty array removes all
     * restrictions.
     *
     * @param  array<string, list<int>>  $scopes  DataScopeType value => ids
     */
    public function syncDataScopes(User $admin, array $scopes): void
    {
        $before = $admin->dataScopeIds();
        $after = collect($scopes)
            ->map(fn (array $ids): array => collect($ids)->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all())
            ->filter()
            ->all();

        ksort($before);
        ksort($after);

        if (array_map(fn (array $ids): array => collect($ids)->sort()->values()->all(), $before) === $after) {
            return;
        }

        $admin->dataScopes()->delete();

        foreach ($after as $type => $ids) {
            $admin->dataScopes()->createMany(
                array_map(fn (int $id): array => ['scope_type' => $type, 'scope_id' => $id], $ids),
            );
        }

        $admin->unsetRelation('dataScopes');

        $this->audit->log('admin_user.data_scopes_updated', $admin, ['scopes' => $before], ['scopes' => $after]);
    }
}
